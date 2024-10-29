<?php
$AF_ID = isset($_COOKIE['AFF_ID']) ? sanitize_text_field($_COOKIE['AFF_ID']) : null;

if ($AF_ID && !is_admin()) {
    add_action('user_register', 'affilio_call_after_new_customer_insert');
    add_action('woocommerce_order_status_changed', 'affilio_call_after_order_update', 10, 3);
    add_action('woocommerce_order_status_cancelled', 'affilio_call_after_order_cancel');
    add_action('woocommerce_new_order', 'affilio_call_after_order_new_order', 10, 2);
    // add_action('woocommerce_process_shop_order_meta', 'affilio_call_after_order_update_2', 10 );

    // add_action('woocommerce_update_order_item', 'affilio_call_after_order_update_2', 10, 2);
    // add_action('woocommerce_new_order_item', 'affilio_call_after_order_update_2', 10, 2);
    // add_action('woocommerce_update_order_item_meta', 'affilio_call_after_order_update_2', 10, 4);
    $bearer = get_option("affilio_token");
    define('AFFILIO_BEARER', $bearer);
}
if (is_admin()) {
    $bearer = get_option("affilio_token");
    define('AFFILIO_BEARER', $bearer);
    add_action('woocommerce_order_status_changed', 'affilio_call_after_order_update_admin', 10, 3);
}
// function affilio_call_after_order_update_2 ($id, $b, $c, $r){
//     // affilio_log_me($og);
// }

function affilio_call_after_new_customer_insert($user_id)
{
    $AF_ID = isset($_COOKIE['AFF_ID']) ? sanitize_text_field($_COOKIE['AFF_ID']) : null;
    $options = get_option('affilio_option_name');
    $webstore = $options['webstore'];
    $body = array(array(
        "user_id" => $user_id,
        "web_store_code" => $webstore,
        "affiliate_id" => $AF_ID
    ));

    $params = array(
        'body'    => wp_json_encode($body),
        // 'timeout' => 60,
        'headers' => array(
            'Content-Type' => 'application/json;charset=' . get_bloginfo('charset'),
            'Authorization' => 'Bearer ' . AFFILIO_BEARER,
        ),
    );
    $response = wp_safe_remote_post(affilio_get_url(AFFILIO_SYNC_NEW_CUSTOMER_API), $params);
    if ($GLOBALS['is_debug']) {
        affilio_set_log($body, "PLUGIN_WP_SYNC_ORDER_DATA");
        affilio_set_log($response, "PLUGIN_WP_SYNC_ORDER");
    }
    // affilio_log_me($response);
    if (is_wp_error($response)) {
        affilio_set_log($response, "PLUGIN_WP_SYNC_ORDER");
        return $response;
    } elseif (empty($response['body'])) {
        return new WP_Error('AFFILIO-api', 'Empty Response');
    }
}

function affilio_call_after_order_update_admin($id, $pre, $next)
{
    $order_ = wc_get_order(
        $id
    );
    $affId = wc_get_order_item_meta($id, '_aff_id');

    $affilio_options = get_option('affilio_option_name');
    $status_finalize = $affilio_options['status_finalize'];

    if ($next == "completed" || $next == $status_finalize || $next == "cancelled") {
        // set status admin approved
        $body = [];
        // $order_ = wc_get_order($id);
        $orders = array($order_);
        $body = [];
        
        foreach ($orders as $order) :
            $orderModel = affilio_order_model($order, $affId);
            array_push($body, $orderModel);
        endforeach;

        $params = array(
            'body'    => json_encode($body),
            // 'timeout' => 60,
            'headers' => array(
                'Content-Type' => 'application/json;charset=' . get_bloginfo('charset'),
                'Authorization' => 'Bearer ' . AFFILIO_BEARER,
            ),
        );

        $response = wp_safe_remote_post(affilio_get_url(AFFILIO_SYNC_ORDER_API), $params);
        $isSuccess = json_decode($response['body'])->success;
        if (!$isSuccess) {
            affilio_set_log($response, "PLUGIN_WP_SYNC_ORDER");
        }

        if ($GLOBALS['is_debug']) {
            affilio_set_log($body, "PLUGIN_WP_SYNC_ORDER_DATA");
            affilio_set_log($response, "PLUGIN_WP_SYNC_ORDER");
        }

        if (is_wp_error($response)) {
            affilio_set_log($response, "PLUGIN_WP_SYNC_ORDER");
            return $response;
        } elseif (empty($response['body'])) {
            return new WP_Error('AFFILIO-api', 'Empty Response');
        }
    }
}

function affilio_call_after_order_new_order($id, $orderLL)
{
    $AF_ID = isset($_COOKIE['AFF_ID']) ? sanitize_text_field($_COOKIE['AFF_ID']) : null;

    if (isset($AF_ID)) {
        $isExist = wc_get_order_item_meta($id, '_aff_id');
        if (!$isExist) {
            wc_add_order_item_meta($id, '_aff_id', $AF_ID);
        }
    } else {
        return;
    }
    $order_ = wc_get_order($id);
    if ( ! $order_ ) {
        return -1;
    }
    $body = [];
    $orderModel = affilio_order_model($orderLL, $AF_ID);
    array_push($body, $orderModel);

    affilio_call_sync_order_list_api($id, $body);
}

function affilio_call_sync_order_list_api($id, $body)
{
    $params = array(
        'body'    => json_encode($body),
        'headers' => array(
            'Content-Type' => 'application/json;charset=' . get_bloginfo('charset'),
            'Authorization' => 'Bearer ' . AFFILIO_BEARER,
        ),
    );

    $response = wp_safe_remote_post(affilio_get_url(AFFILIO_SYNC_ORDER_API), $params);
    $isSuccess = json_decode($response['body'])->success;

    if ($isSuccess) {
        affilio_set_option(AFFILIO_LAST_ORDER, $id);
    }

    if ($GLOBALS['is_debug']) {
        affilio_set_log($body, "PLUGIN_WP_SYNC_ORDER_DATA");
        affilio_set_log($response, "PLUGIN_WP_SYNC_ORDER");
    }

    if (is_wp_error($response)) {
        return $response;
    } elseif (empty($response['body'])) {
        return new WP_Error('AFFILIO-api', 'Empty Response');
    }
}

function affilio_get_order_item_mode($order, $orderItem){
    $order_item_id = $orderItem->get_id();
    // $product = $orderItem->get_product();
    $product = wc_get_product( $orderItem->get_product_id() );
    $cats = $product->get_category_ids();
    $cat = end($cats);

    return array(
        'product_id'                 => $orderItem->get_product_id(),
        "variant_id"                 => $orderItem->get_variation_id(),
        'current_product_price'      => $product->get_regular_price(),
        // 'current_product_price'      => wc_format_decimal( $order->get_item_total( $orderItem ), 0 ),
        // "product_rrp_price"          => 0,
        // "discount"                   => wc_format_decimal( $order->get_total_discount(), 0 ),
        'discount' => $product->get_price() ? $product->get_regular_price() - $product->get_price() : null,
        // "promotion_discount"         => $product->get_sale_price() - $product->get_price(),
        "product_leaf_category"      => $cat,
        "quantity"                   => $orderItem->get_quantity(),
        "vat_price"                  => wc_format_decimal( $order->get_line_tax( $orderItem ), 0 ),
        // "voucher_code" => "string",
        // "voucher_type" => "string",
        // "voucher_percent" => 0,
        // "voucher_price" => 0
    );
}

function affilio_order_model($order, $affId)
{
    $orderItems  = [];
    $order_items = $order->get_items();

    foreach ($order_items as $orderItem) :
        $oItem = affilio_get_order_item_mode($order, $orderItem);
        array_push($orderItems, $oItem);
    endforeach;

    $options  = get_option('affilio_option_name');
    $webstore = $options['webstore'];

    $val = array(
        'basket_id' => "wp-".$order->id, //$order->order_key,
        'order_id' => "wp-".$order->id,
        'web_store_id' => $webstore,
        'affiliate_id' => $affId,
        'is_new_customer' => '',
        // 'order_status' => $order->status,
        'order_status' => afiilio_get_order_status($order->status),
        'shipping_cost' => $order->shipping_total,
        'discount' => $order->discount_total,
        'order_amount' => $order->total,
        'source' => '',
        'created_at' => afiilio_get_time($order->get_date_created()), //"2022-10-12 07:40:41.000000",
        'close_source' => '',
        'state' => $order->billing->city,
        'city' => $order->billing->city,
        'user_id' => $order->customer_id,
        'voucher_code' => '',
        'voucher_type' => '',
        'voucher_price' => $order->discount_total,
        'vat_price' => $order->total_tax,
        'voucher_percent' => '',
        // 'update_date' => $order->date_modified->date,
        'update_date' => afiilio_get_time($order->get_date_modified()), //"2022-10-12 07:40:41.000000",
        // 'delivery_date' => $order->date_completed->date,
        'delivery_date' => $order->date_completed ? afiilio_get_time($order->get_date_completed()) : null, //"2022-10-12 07:40:41.000000",
        'voucher_used_amount' => '',
        'order_items' => $orderItems
    );

    // affilio_log_me($val);
    return $val;
}

function affilio_call_after_order_update($id, $pre, $next)
{
    $AF_ID = isset($_COOKIE['AFF_ID']) ? sanitize_text_field($_COOKIE['AFF_ID']) : null;
    if (isset($AF_ID)) {
        $isExist = wc_get_order_item_meta($id, '_aff_id');
        if (!$isExist) {
            wc_add_order_item_meta($id, '_aff_id', $AF_ID);
        }
    } else {
        return;
    }

    $options = get_option('affilio_option_name');
    $order_ = wc_get_order($id);

    $body = [];
    $orderModel = affilio_order_model($order_, $AF_ID);
    array_push($body, $orderModel);

    $params = array(
        'body'    => json_encode($body),
        // 'timeout' => 60,
        'headers' => array(
            'Content-Type' => 'application/json;charset=' . get_bloginfo('charset'),
            'Authorization' => 'Bearer ' . AFFILIO_BEARER,
        ),
    );

    affilio_log_me($params);

    if ($pre === 'pending' && $next === 'processing') {
        affilio_call_sync_order_list_api($id, $body);
    }
    if ($next === 'canceled') {
        $response = wp_safe_remote_post(affilio_get_url(AFFILIO_SYNC_ORDER_CANCEL_API), $params);
        $isSuccess = json_decode($response['body'])->success;
        // affilio_log_me($isSuccess);
        if (!$isSuccess) {
            affilio_set_log($response, "PLUGIN_WP_SYNC_ORDER");
            // affilio_set_option(AFFILIO_LAST_ORDER, $id);
        }
        if ($GLOBALS['is_debug']) {
            affilio_set_log($body, "PLUGIN_WP_SYNC_ORDER_DATA");
            affilio_set_log($response, "PLUGIN_WP_SYNC_ORDER");
        }

        if (is_wp_error($response)) {
            affilio_set_log($response, "PLUGIN_WP_SYNC_ORDER");
            return $response;
        } elseif (empty($response['body'])) {
            return new WP_Error('AFFILIO-api', 'Empty Response');
        }
    }
    if ($next) {
        $response = wp_safe_remote_post(affilio_get_url(AFFILIO_SYNC_ORDER_UPDATE_API), $params);
        $isSuccess = json_decode($response['body'])->success;
        // affilio_log_me($isSuccess);

        if (!$isSuccess) {
            affilio_set_log($response, "PLUGIN_WP_SYNC_ORDER");
            // affilio_set_option(AFFILIO_LAST_ORDER, $id);
        }
        if ($GLOBALS['is_debug']) {
            affilio_set_log($body, "PLUGIN_WP_SYNC_ORDER_DATA");
            affilio_set_log($response, "PLUGIN_WP_SYNC_ORDER");
        }

        if (is_wp_error($response)) {
            affilio_set_log($response, "PLUGIN_WP_SYNC_ORDER");
            return $response;
        } elseif (empty($response['body'])) {
            return new WP_Error('AFFILIO-api', 'Empty Response');
        }
    }
}

function affilio_call_after_order_cancel($order_id)
{
    $body = array(array(
        "order_id" => $order_id,
        "basket_id" => "string",
        "web_store_code" => AFFILIO_WEB_STORE_ID
    ));

    $params = array(
        'body'    => wp_json_encode($body),
        // 'timeout' => 60,
        'headers' => array(
            'Content-Type' => 'application/json;charset=' . get_bloginfo('charset'),
            'Authorization' => 'Bearer ' . AFFILIO_BEARER,
        ),
    );
    $response = wp_safe_remote_post(affilio_get_url(AFFILIO_SYNC_ORDER_CANCEL_API), $params);

    if ($GLOBALS['is_debug']) {
        affilio_set_log($body, "PLUGIN_WP_SYNC_ORDER_DATA");
        affilio_set_log($response, "PLUGIN_WP_SYNC_ORDER");
    }

    if (is_wp_error($response)) {
        affilio_set_log($response, "PLUGIN_WP_SYNC_ORDER");
        return $response;
    } elseif (empty($response['body'])) {
        return new WP_Error('AFFILIO-api', 'Empty Response');
    }
    parse_str($response['body'], $response_);
}

// TEST METHOD
// add_action( 'woocommerce_loaded', 'testfunctiontest' );
add_action('init', 'affilio_init', 10, 0);
function affilio_init()
{
    // $local_tz = new \DateTimeZone(wc_timezone_string());
    // $order_ = wc_get_order(absint(86));
}

function afiilio_get_time($time)
{
    if(!$time) return null;
    // return format_datetime( $coupon->get_date_created() ? $coupon->get_date_created()->getTimestamp() : 0 );
    // return gmdate($time->date('Y-m-d H:i:s'));
    return gmdate( 'Y-m-d H:i:s', $time->getTimestamp());
}

function afiilio_get_order_status($status)
{
    // 'pending'    => 'https://schema.org/OrderPaymentDue',
    // 'processing' => 'https://schema.org/OrderProcessing',
    // 'on-hold'    => 'https://schema.org/OrderProblem',
    // 'completed'  => 'https://schema.org/OrderDelivered',
    // 'cancelled'  => 'https://schema.org/OrderCancelled',
    // 'refunded'   => 'https://schema.org/OrderReturned',
    // 'failed'     => 'https://schema.org/OrderProblem',
    $rtn = 1;
    $affilio_order_status = array(
        "New" => 1,
        "MerchantApproved" => 2,
        "Finalize" => 3,
        "Canceled" => 4,
    );

    $affilio_options = get_option('affilio_option_name');
    $status_finalize = $affilio_options['status_finalize'];

    if ($status_finalize == $status) {
        $rtn = $affilio_order_status["Finalize"];
        return $rtn;
    }

    switch ($status) {
        case 'completed':
            if ($status_finalize != "completed") {
                $rtn = $affilio_order_status["MerchantApproved"];
            } else {
                $rtn = $affilio_order_status["Finalize"];
            }
            break;
        case 'delivered':
            // $rtn = $affilio_order_status["Finalize"];
            $rtn = $affilio_order_status["MerchantApproved"];
            break;
        case 'pending':
        case 'on-hold':
        case 'processing':
            $rtn = $affilio_order_status["New"];
            break;
        case 'cancelled':
        case 'failed':
            $rtn = $affilio_order_status["Canceled"];
            break;
    }
    return $rtn;
}
