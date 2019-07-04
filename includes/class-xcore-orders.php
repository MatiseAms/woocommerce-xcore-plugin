<?php
defined('ABSPATH') || exit;

class Xcore_Orders extends WC_REST_Orders_Controller
{
    protected static $_instance = null;
    public           $version   = '1';
    public           $namespace = 'wc-xcore/v1';
    public           $base      = 'orders';

    public static function instance()
    {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    public function __construct()
    {
        $this->init();
    }

    /**
     * Register all order routes
     */
    public function init()
    {
        register_rest_route($this->namespace, $this->base, array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array($this, 'get_items'),
            'permission_callback' => array($this, 'get_items_permissions_check'),
            'args'                => $this->get_collection_params(),
        ));

        register_rest_route($this->namespace, $this->base . '/statuses', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array($this, 'get_orders_statuses'),
            'permission_callback' => array($this, 'get_items_permissions_check'),
            'args'                => $this->get_collection_params(),
        ));

        register_rest_route($this->namespace, $this->base . '/(?P<id>[\d]+)', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array($this, 'get_item'),
            'permission_callback' => array($this, 'get_item_permissions_check'),
            'args'                => $this->get_collection_params(),
        ));
    }

    /**
     * @param WP_REST_Request $request
     * @return WP_Error|WP_REST_Response
     */
    public function get_item($request)
    {
        $response = parent::get_item($request);
        $types = ['line_items', 'shipping_lines', 'fee_lines'];

        foreach ($response->data['line_items'] as $line_key => $line_item) {
            $has_embroidery_1 = false;
            $has_embroidery_2 = false;
            foreach ($line_item['meta_data'] as $meta_key => $meta_data) {
                if($meta_data->get_data()['key'] === 'text1'){
                    error_log('Embroidery line 1: '.$meta_data->get_data()['value']);
                    $has_embroidery_1 = true;
                }
                if(strpos($meta_data->get_data()['key'], 'text2') > -1){
                    error_log('Embroidery line 2: '.$meta_data->get_data()['value']);
                    $has_embroidery_2 = true;
                }
                if($meta_data->get_data()['key'] === 'font'){
                    error_log('Font: '.$meta_data->get_data()['value']);
                }
                if($meta_data->get_data()['key'] === 'color' && $has_embroidery_1){
                    error_log('Color: '.$meta_data->get_data()['value']);
                }
                if($meta_data->get_data()['key'] === 'position'){
                    error_log('Position: '.$meta_data->get_data()['value']);
                }

            }

            if($has_embroidery_1){
                $response->data['line_items'][$line_key]['price'] = $response->data['line_items'][$line_key]['price'] - 12.95;
                $response->data['line_items'][] = [
                    "id" => 0,
                    "name" => "Embroidery line 1",
                    "product_id" => 0,
                    "variation_id" => 0,
                    "quantity" => 1,
                    "tax_class" => "",
                    "subtotal" => "12.95",
                    "subtotal_tax" => "2.72",
                    "total" => "12.95",
                    "total_tax" => "2.72",
                    "taxes" => [
                        [
                            "id" => 21,
                            "rate" => "21.0000",
                            "total" => "2.72",
                            "subtotal" => "2.72"
                        ]
                    ],
                    "meta_data" => [],
                    "sku" => "9810670001101",
                    "price" => 12.95,
                    "bundled_by" => "",
                    "bundled_item_title" => "",
                    "bundled_items" => []
                ];
            }
            if($has_embroidery_2){
                $response->data['line_items'][$line_key]['price'] = $response->data['line_items'][$line_key]['price'] - 3.5;
                $response->data['line_items'][] = [
                    "id" => 0,
                    "name" => "Embroidery line 2",
                    "product_id" => 0,
                    "variation_id" => 0,
                    "quantity" => 1,
                    "tax_class" => "",
                    "subtotal" => "3.50",
                    "subtotal_tax" => "0.74",
                    "total" => "3.50",
                    "total_tax" => "0.74",
                    "taxes" => [
                        [
                            "id" => 21,
                            "rate" => "21.0000",
                            "total" => "0.74",
                            "subtotal" => "0.74"
                        ]
                    ],
                    "meta_data" => [],
                    "sku" => "9810670001102",
                    "price" => 3.50,
                    "bundled_by" => "",
                    "bundled_item_title" => "",
                    "bundled_items" => []
                ];
            }
        }


        foreach($types as $type) {
            Xcore_Helper::add_tax_rate($response->data, $type);
        }

        return $response;
    }

    /**
     * Prepare a single order output for response.
     *
     * @since  3.0.0
     * @param  WC_Data         $object  Object data.
     * @param  WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public function prepare_object_for_response( $object, $request ) {
        $this->request       = $request;
        $this->request['dp'] = is_null( $this->request['dp'] ) ? wc_get_price_decimals() : absint( $this->request['dp'] );
        $data                = $this->get_formatted_item_data( $object );
        $context             = ! empty( $request['context'] ) ? $request['context'] : 'view';
        $data                = $this->add_additional_fields_to_object( $data, $request );
        $data                = $this->filter_response_by_context( $data, $context );
        $response            = rest_ensure_response( $data );
        $response->add_links( $this->prepare_links( $object, $request ) );

        /**
         * Filter the data for a response.
         *
         * The dynamic portion of the hook name, $this->post_type,
         * refers to object type being prepared for the response.
         *
         * @param WP_REST_Response $response The response object.
         * @param WC_Data          $object   Object data.
         * @param WP_REST_Request  $request  Request object.
         */
        return apply_filters( "woocommerce_rest_prepare_{$this->post_type}_object", $response, $object, $request );
    }

    /**
     * Get formatted item data.
     *
     * @since  3.0.0
     * @param  WC_Data $object WC_Data instance.
     * @return array
     */
    protected function get_formatted_item_data( $object ) {
        $data              = $object->get_data();
        $format_decimal    = array( 'discount_total', 'discount_tax', 'shipping_total', 'shipping_tax', 'shipping_total', 'shipping_tax', 'cart_tax', 'total', 'total_tax' );
        $format_date       = array( 'date_created', 'date_modified', 'date_completed', 'date_paid' );
        $format_line_items = array( 'line_items', 'tax_lines', 'shipping_lines', 'fee_lines', 'coupon_lines' );

        // Format decimal values.
        foreach ( $format_decimal as $key ) {
            $data[ $key ] = wc_format_decimal( $data[ $key ], $this->request['dp'] );
        }

        // Format date values.
        foreach ( $format_date as $key ) {
            $datetime              = $data[ $key ];
            $data[ $key ]          = wc_rest_prepare_date_response( $datetime, false );
            $data[ $key . '_gmt' ] = wc_rest_prepare_date_response( $datetime );
        }

        // Format the order status.
        $data['status'] = 'wc-' === substr( $data['status'], 0, 3 ) ? substr( $data['status'], 3 ) : $data['status'];

        // Format line items.
        foreach ( $format_line_items as $key ) {
            $data[ $key ] = array_values( array_map( array( $this, 'get_order_item_data' ), $data[ $key ] ) );
        }

        // Refunds.
        $data['refunds'] = array();
        foreach ( $object->get_refunds() as $refund ) {
            $data['refunds'][] = array(
                'id'     => $refund->get_id(),
                'reason' => $refund->get_reason() ? $refund->get_reason() : '',
                'total'  => '-' . wc_format_decimal( $refund->get_amount(), $this->request['dp'] ),
            );
        }

        return array(
            'id'                   => $object->get_id(),
            'parent_id'            => $data['parent_id'],
            'number'               => $data['number'],
            'order_key'            => $data['order_key'],
            'created_via'          => $data['created_via'],
            'version'              => $data['version'],
            'status'               => $data['status'],
            'currency'             => $data['currency'],
            'date_created'         => $data['date_created'],
            'date_created_gmt'     => $data['date_created_gmt'],
            'date_modified'        => $data['date_modified'],
            'date_modified_gmt'    => $data['date_modified_gmt'],
            'discount_total'       => $data['discount_total'],
            'discount_tax'         => $data['discount_tax'],
            'shipping_total'       => $data['shipping_total'],
            'shipping_tax'         => $data['shipping_tax'],
            'cart_tax'             => $data['cart_tax'],
            'total'                => $data['total'],
            'total_tax'            => $data['total_tax'],
            'prices_include_tax'   => $data['prices_include_tax'],
            'customer_id'          => $data['customer_id'],
            'customer_ip_address'  => $data['customer_ip_address'],
            'customer_user_agent'  => $data['customer_user_agent'],
            'customer_note'        => $data['customer_note'],
            'billing'              => $data['billing'],
            'shipping'             => $data['shipping'],
            'payment_method'       => $data['payment_method'],
            'payment_method_title' => $data['payment_method_title'],
            'transaction_id'       => $data['transaction_id'],
            'date_paid'            => $data['date_paid'],
            'date_paid_gmt'        => $data['date_paid_gmt'],
            'date_completed'       => $data['date_completed'],
            'date_completed_gmt'   => $data['date_completed_gmt'],
            'cart_hash'            => $data['cart_hash'],
            'meta_data'            => $data['meta_data'],
            'line_items'           => $data['line_items'],
            'tax_lines'            => $data['tax_lines'],
            'shipping_lines'       => $data['shipping_lines'],
            'fee_lines'            => $data['fee_lines'],
            'coupon_lines'         => $data['coupon_lines'],
            'refunds'              => $data['refunds'],
        );
    }

    /**
     * Expands an order item to get its data.
     *
     * @param WC_Order_item $item Order item data.
     * @return array
     */
    protected function get_order_item_data( $item ) {
        $data           = $item->get_data();
        $format_decimal = array( 'subtotal', 'subtotal_tax', 'total', 'total_tax', 'tax_total', 'shipping_tax_total' );

        // Format decimal values.
        foreach ( $format_decimal as $key ) {
            if ( isset( $data[ $key ] ) ) {
                $data[ $key ] = wc_format_decimal( $data[ $key ], $this->request['dp'] );
            }
        }

        // Add SKU and PRICE to products.
        if ( is_callable( array( $item, 'get_product' ) ) ) {
            $data['sku']   = $item->get_product() ? $item->get_product()->get_sku() : null;
            $data['price'] = $item->get_quantity() ? $item->get_total() / $item->get_quantity() : 0;
        }

        // Format taxes.
        if ( ! empty( $data['taxes']['total'] ) ) {
            $taxes = array();
            $rates = WC_Tax::get_rates( $item->get_tax_class() );

            foreach ( $data['taxes']['total'] as $tax_rate_id => $tax ) {
                $taxes[] = array(
                    'id'       => $tax_rate_id,
                    'rate'     => $rates[$tax_rate_id]['rate'] ?? 0,
                    'total'    => $tax,
                    'subtotal' => isset( $data['taxes']['subtotal'][ $tax_rate_id ] ) ? $data['taxes']['subtotal'][ $tax_rate_id ] : '',
                );
            }
            $data['taxes'] = $taxes;

        } elseif ( isset( $data['taxes'] ) ) {
            $data['taxes'] = array();
        }

        // Remove names for coupons, taxes and shipping.
        if ( isset( $data['code'] ) || isset( $data['rate_code'] ) || isset( $data['method_title'] ) ) {
            unset( $data['name'] );
        }

        // Remove props we don't want to expose.
        unset( $data['order_id'] );
        unset( $data['type'] );

        return $data;
    }

    /**
     * @param WP_REST_Request $request
     * @return array|WP_Error|WP_REST_Response
     * @throws Exception
     */
    public function get_items($request)
    {
        $limit = (int)$request['limit'] ?: 50;
        $date  = $request['date_modified'] ?: '2001-01-01 00:00:00';

        $orders = new WP_Query(array(
                                   'numberposts'    => -1,
                                   'post_type'      => 'shop_order',
                                   'post_status'    => array_keys(wc_get_order_statuses()),
                                   'posts_per_page' => $limit,
                                   'orderby'        => 'post_modified',
                                   'order'          => 'ASC',
                                   'date_query'     => array(
                                       array(
                                           'column' => 'post_modified_gmt',
                                           'after'  => $date
                                       )
                                   )
                               ));

        $result = [];

        foreach ($orders->get_posts() as $order) {
            $data['id']            = $order->ID;
            $data['date_created']  = new WC_DateTime($order->post_date_gmt);
            $data['date_modified'] = new WC_DateTime($order->post_modified_gmt);

            $result[] = $data;
        }

        return $result;
    }

    /**
     * @return array
     */
    public function get_orders_statuses()
    {
        return wc_get_order_statuses();
    }
}
