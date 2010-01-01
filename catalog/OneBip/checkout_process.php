<?php
  /*
   * OneBip Payment System 
   * 
   * Developed by Dynamo Effects
   * Copyright 2007 OneBip
   *
   * Based off of checkout_process.php by hpdl of osCommerce
   *
   * Released under the GNU General Public License Version 2
   */

  chdir('..');

  include('includes/application_top.php');

// if the customer is not logged on, redirect them to the login page
  if (!tep_session_is_registered('customer_id')) {
    $navigation->set_snapshot(array('page' => FILENAME_ONEBIP_DIR . FILENAME_CHECKOUT_PROCESS,
                                    'mode' => 'SSL',
                                    'get' => $_GET,
                                    'post' => $_POST));
                                    
    tep_redirect(tep_href_link(FILENAME_LOGIN, '', 'SSL'));
  }

  $productIDRaw = preg_replace('/[^0-9{}]/', '', $_GET['products_id']);

  $productID = tep_get_prid($productIDRaw);
  
  if (!$OneBip->ProductStatus($productID)) {
    tep_redirect(tep_href_link(FILENAME_DEFAULT));
  }
  
  include(DIR_WS_LANGUAGES . $language . '/' . FILENAME_CHECKOUT_PROCESS);

  require(DIR_WS_CLASSES . 'order.php');
  $order = new order;
  
  $order->info['order_status'] = ONEBIP_ORDER_STATUS_PENDING_1;

  include(DIR_WS_LANGUAGES . $language . '/modules/order_total/ot_total.php');

  $products_query = tep_db_query("SELECT p.products_id," .
                                 " pd.products_name," .
                                 " p.products_model," .
                                 " p.products_image," .
                                 " p.products_price," .
                                 " p.products_weight," .
                                 " p.products_tax_class_id" .
                                 " FROM " . TABLE_PRODUCTS . " p," .
                                 TABLE_PRODUCTS_DESCRIPTION . " pd" .
                                 " WHERE p.products_id = " . (int)$productID . 
                                 "   AND pd.products_id = p.products_id" .
                                 "   AND pd.language_id = " . (int)$languages_id .
                                 " LIMIT 1");

  $products = tep_db_fetch_array($products_query);
                                 
  $productPrice = $products['products_price'];

  $specials_query = tep_db_query("SELECT specials_new_products_price" .
                                 " FROM " . TABLE_SPECIALS . 
                                 " WHERE products_id = " . (int)$productID .
                                 "   AND status = '1'");
                                 
  if (tep_db_num_rows($specials_query)) {
    $specials = tep_db_fetch_array($specials_query);
    $productPrice = $specials['specials_new_products_price'];
  }

  $order->products = array(array('qty' => 1,
                                 'name' => $products['products_name'],
                                 'model' => $products['products_model'],
                                 'tax' => 0,
                                 'tax_description' => 'Not Taxed',
                                 'price' => $productPrice,
                                 'final_price' => $productPrice,
                                 'weight' => $products['products_weight'],
                                 'id' => $productIDRaw,
                                 ));
                             
  $options = $OneBip->UnserializeAttributes($productIDRaw);
  
  if (count($options)) {
    $order->products[0]['attributes'] = array();
    
    foreach ($options as $optionID => $attID) {
      $attributes_query = tep_db_query("SELECT popt.products_options_name," .
                                       " poval.products_options_values_name" .
                                       " FROM " . TABLE_PRODUCTS_OPTIONS . " popt," .
                                       TABLE_PRODUCTS_OPTIONS_VALUES . " poval," .
                                       TABLE_PRODUCTS_ATTRIBUTES . " pa" .
                                       " WHERE pa.products_id = " . (int)$productID .
                                       "   AND pa.options_id = " . (int)$optionID .
                                       "   AND pa.options_id = popt.products_options_id" .
                                       "   AND pa.options_values_id = " . (int)$attID .
                                       "   AND pa.options_values_id = poval.products_options_values_id" .
                                       "   AND popt.language_id = " . (int)$languages_id .
                                       "   AND poval.language_id = " . (int)$languages_id);
                                       
      $attributes = tep_db_fetch_array($attributes_query);

      /* Attribute prices are set to $0 because OneBip only allows
       * specific price points
       */
      $order->products[0]['attributes'][] = array('option' => $attributes['products_options_name'],
                                                  'value' => $attributes['products_options_values_name'],
                                                  'option_id' => $optionID,
                                                  'value_id' => $attID,
                                                  'prefix' => '+',
                                                  'price' => 0);
    }
  }
  
  $order->delivery = $order->customer;
  $order->billing = $order->customer;
  
  $order->info['payment_method'] = 'OneBip';
  $order->info['subtotal'] = $productPrice;
  $order->info['tax'] = 0;

  $order->info['total'] = $productPrice;

  /* Manually create ot_total values */
  $order_totals = array(array('code' => 'ot_total',
                              'title' => MODULE_ORDER_TOTAL_TOTAL_TITLE,
                              'text' => '<b>' . $currencies->format($productPrice, true, $order->info['currency'], $order->info['currency_value']) . '</b>',
                              'value' => $productPrice,
                              'sort_order' => 1));

  /* Since everything gets passed with $_GET vars, 
     this is an extra security measure to prevent
     someone from trying to update orders that aren't
     their own. 
   */
  $orderUniqueID = tep_create_random_value(30, 'mixed');
                              
  $sql_data_array = array('customers_id' => $customer_id,
                          'customers_name' => $order->customer['firstname'] . ' ' . $order->customer['lastname'],
                          'customers_company' => $order->customer['company'],
                          'customers_street_address' => $order->customer['street_address'],
                          'customers_suburb' => $order->customer['suburb'],
                          'customers_city' => $order->customer['city'],
                          'customers_postcode' => $order->customer['postcode'], 
                          'customers_state' => $order->customer['state'], 
                          'customers_country' => $order->customer['country']['title'], 
                          'customers_telephone' => $order->customer['telephone'], 
                          'customers_email_address' => $order->customer['email_address'],
                          'customers_address_format_id' => $order->customer['format_id'], 
                          'delivery_name' => $order->delivery['firstname'] . ' ' . $order->delivery['lastname'], 
                          'delivery_company' => $order->delivery['company'],
                          'delivery_street_address' => $order->delivery['street_address'], 
                          'delivery_suburb' => $order->delivery['suburb'], 
                          'delivery_city' => $order->delivery['city'], 
                          'delivery_postcode' => $order->delivery['postcode'], 
                          'delivery_state' => $order->delivery['state'], 
                          'delivery_country' => $order->delivery['country']['title'], 
                          'delivery_address_format_id' => $order->delivery['format_id'], 
                          'billing_name' => $order->customer['firstname'] . ' ' . $order->customer['lastname'], 
                          'billing_company' => $order->customer['company'],
                          'billing_street_address' => $order->customer['street_address'], 
                          'billing_suburb' => $order->customer['suburb'], 
                          'billing_city' => $order->customer['city'], 
                          'billing_postcode' => $order->customer['postcode'], 
                          'billing_state' => $order->customer['state'], 
                          'billing_country' => $order->customer['country']['title'], 
                          'billing_address_format_id' => $order->customer['format_id'], 
                          'payment_method' => $order->info['payment_method'], 
                          'date_purchased' => 'now()', 
                          'last_modified' => 'now()',
                          'orders_status' => $order->info['order_status'], 
                          'currency' => $order->info['currency'], 
                          'currency_value' => $order->info['currency_value'],
                          'onebip_unique' => $orderUniqueID);
  tep_db_perform(TABLE_ORDERS, $sql_data_array);
  
  /*
   * Order Totals
   */
  $insert_id = tep_db_insert_id();
  for ($i=0, $n=sizeof($order_totals); $i<$n; $i++) {
    $sql_data_array = array('orders_id' => $insert_id,
                            'title' => $order_totals[$i]['title'],
                            'text' => $order_totals[$i]['text'],
                            'value' => $order_totals[$i]['value'], 
                            'class' => $order_totals[$i]['code'], 
                            'sort_order' => $order_totals[$i]['sort_order']);
    tep_db_perform(TABLE_ORDERS_TOTAL, $sql_data_array);
  }

  $customer_notification = (SEND_EMAILS == 'true') ? '1' : '0';
  $sql_data_array = array('orders_id' => $insert_id, 
                          'orders_status_id' => $order->info['order_status'], 
                          'date_added' => 'now()', 
                          'customer_notified' => $customer_notification,
                          'comments' => ONEBIP_ORDER_STATUS_PENDING_1_COMMENT);
                          
  tep_db_perform(TABLE_ORDERS_STATUS_HISTORY, $sql_data_array);

// initialized for the email confirmation
  $products_ordered = '';
  $subtotal = 0;
  $total_tax = 0;

  for ($i=0, $n=sizeof($order->products); $i<$n; $i++) {
// Stock Update - Joao Correia
    if (STOCK_LIMITED == 'true') {
      if (DOWNLOAD_ENABLED == 'true') {
        $stock_query_raw = "SELECT products_quantity, pad.products_attributes_filename 
                            FROM " . TABLE_PRODUCTS . " p
                            LEFT JOIN " . TABLE_PRODUCTS_ATTRIBUTES . " pa
                             ON p.products_id=pa.products_id
                            LEFT JOIN " . TABLE_PRODUCTS_ATTRIBUTES_DOWNLOAD . " pad
                             ON pa.products_attributes_id=pad.products_attributes_id
                            WHERE p.products_id = '" . tep_get_prid($order->products[$i]['id']) . "'";
                            
        // Will work with only one option for downloadable products
        // otherwise, we have to build the query dynamically with a loop
        $products_attributes = $order->products[$i]['attributes'];
        if (is_array($products_attributes)) {
          $stock_query_raw .= " AND pa.options_id = '" . $products_attributes[0]['option_id'] . "' AND pa.options_values_id = '" . $products_attributes[0]['value_id'] . "'";
        }
        $stock_query = tep_db_query($stock_query_raw);
      } else {
        $stock_query = tep_db_query("select products_quantity from " . TABLE_PRODUCTS . " where products_id = '" . tep_get_prid($order->products[$i]['id']) . "'");
      }
      if (tep_db_num_rows($stock_query) > 0) {
        $stock_values = tep_db_fetch_array($stock_query);
// do not decrement quantities if products_attributes_filename exists
        if ((DOWNLOAD_ENABLED != 'true') || (!$stock_values['products_attributes_filename'])) {
          $stock_left = $stock_values['products_quantity'] - $order->products[$i]['qty'];
        } else {
          $stock_left = $stock_values['products_quantity'];
        }
        tep_db_query("update " . TABLE_PRODUCTS . " set products_quantity = '" . $stock_left . "' where products_id = '" . tep_get_prid($order->products[$i]['id']) . "'");
        if ( ($stock_left < 1) && (STOCK_ALLOW_CHECKOUT == 'false') ) {
          tep_db_query("update " . TABLE_PRODUCTS . " set products_status = '0' where products_id = '" . tep_get_prid($order->products[$i]['id']) . "'");
        }
      }
    }

// Update products_ordered (for bestsellers list)
    tep_db_query("update " . TABLE_PRODUCTS . " set products_ordered = products_ordered + " . sprintf('%d', $order->products[$i]['qty']) . " where products_id = '" . tep_get_prid($order->products[$i]['id']) . "'");

    $sql_data_array = array('orders_id' => $insert_id, 
                            'products_id' => tep_get_prid($order->products[$i]['id']), 
                            'products_model' => $order->products[$i]['model'], 
                            'products_name' => $order->products[$i]['name'], 
                            'products_price' => $order->products[$i]['price'], 
                            'final_price' => $order->products[$i]['final_price'], 
                            'products_tax' => $order->products[$i]['tax'], 
                            'products_quantity' => $order->products[$i]['qty']);
    tep_db_perform(TABLE_ORDERS_PRODUCTS, $sql_data_array);
    $order_products_id = tep_db_insert_id();

//------insert customer choosen option to order--------
    $attributes_exist = '0';
    $products_ordered_attributes = '';
    if (isset($order->products[$i]['attributes'])) {
      $attributes_exist = '1';
      for ($j=0, $n2=sizeof($order->products[$i]['attributes']); $j<$n2; $j++) {
        if (DOWNLOAD_ENABLED == 'true') {
          $attributes_query = "select popt.products_options_name, poval.products_options_values_name, pa.options_values_price, pa.price_prefix, pad.products_attributes_maxdays, pad.products_attributes_maxcount , pad.products_attributes_filename 
                               from " . TABLE_PRODUCTS_OPTIONS . " popt, " . TABLE_PRODUCTS_OPTIONS_VALUES . " poval, " . TABLE_PRODUCTS_ATTRIBUTES . " pa 
                               left join " . TABLE_PRODUCTS_ATTRIBUTES_DOWNLOAD . " pad
                                on pa.products_attributes_id=pad.products_attributes_id
                               where pa.products_id = '" . $order->products[$i]['id'] . "' 
                                and pa.options_id = '" . $order->products[$i]['attributes'][$j]['option_id'] . "' 
                                and pa.options_id = popt.products_options_id 
                                and pa.options_values_id = '" . $order->products[$i]['attributes'][$j]['value_id'] . "' 
                                and pa.options_values_id = poval.products_options_values_id 
                                and popt.language_id = '" . $languages_id . "' 
                                and poval.language_id = '" . $languages_id . "'";
          $attributes = tep_db_query($attributes_query);
        } else {
          $attributes = tep_db_query("select popt.products_options_name, poval.products_options_values_name, pa.options_values_price, pa.price_prefix from " . TABLE_PRODUCTS_OPTIONS . " popt, " . TABLE_PRODUCTS_OPTIONS_VALUES . " poval, " . TABLE_PRODUCTS_ATTRIBUTES . " pa where pa.products_id = '" . $order->products[$i]['id'] . "' and pa.options_id = '" . $order->products[$i]['attributes'][$j]['option_id'] . "' and pa.options_id = popt.products_options_id and pa.options_values_id = '" . $order->products[$i]['attributes'][$j]['value_id'] . "' and pa.options_values_id = poval.products_options_values_id and popt.language_id = '" . $languages_id . "' and poval.language_id = '" . $languages_id . "'");
        }
        $attributes_values = tep_db_fetch_array($attributes);

        $sql_data_array = array('orders_id' => $insert_id, 
                                'orders_products_id' => $order_products_id, 
                                'products_options' => $attributes_values['products_options_name'],
                                'products_options_values' => $attributes_values['products_options_values_name'], 
                                'options_values_price' => $attributes_values['options_values_price'], 
                                'price_prefix' => $attributes_values['price_prefix']);
        tep_db_perform(TABLE_ORDERS_PRODUCTS_ATTRIBUTES, $sql_data_array);

        if ((DOWNLOAD_ENABLED == 'true') && isset($attributes_values['products_attributes_filename']) && tep_not_null($attributes_values['products_attributes_filename'])) {
          if (DOWNLOADS_CONTROLLER_FILEGROUP_STATUS != 'Yes' || !strstr($attributes_values['products_attributes_filename'], 'Group_Files-')) {
            $sql_data_array = array('orders_id' => $insert_id, 
                                    'orders_products_id' => $order_products_id, 
                                    'orders_products_filename' => $attributes_values['products_attributes_filename'], 
                                    'download_maxdays' => $attributes_values['products_attributes_maxdays'], 
                                    'download_count' => $attributes_values['products_attributes_maxcount']);
            tep_db_perform(TABLE_ORDERS_PRODUCTS_DOWNLOAD . '_onebip', $sql_data_array);
          } else {
            $filegroup_array = explode('Group_Files-', $attributes_values['products_attributes_filename']);
            $filegroup_id = $filegroup_array[1];
            $groupfiles_query = tep_db_query("select download_group_filename
                                              from " . TABLE_PRODUCTS_ATTRIBUTES_DOWNLOAD_GROUPS_FILES . "
                                              where download_group_id = '" . (int)$filegroup_id . "'");
            while ($groupfile_array = tep_db_fetch_array($groupfiles_query)) {
              $sql_data_array = array('orders_id' => $insert_id, 
                                      'orders_products_id' => $order_products_id, 
                                      'orders_products_filename' => $groupfile_array['download_group_filename'], 
                                      'download_maxdays' => $attributes_values['products_attributes_maxdays'], 
                                      'download_count' => $attributes_values['products_attributes_maxcount']);
              tep_db_perform(TABLE_ORDERS_PRODUCTS_DOWNLOAD . '_onebip', $sql_data_array);
            }
          }
        }
        
      }
    }
  }
  
  /* Forward the customer to OneBip to complete the purchase */
  header('Location: https://www.onebip.com/otms/?item=' . $OneBip->ProductOneBipID($productID) . '&payment_description=' . urlencode($order->products[0]['name']) . '&merchantParam=' . urlencode('u=' . $orderUniqueID));
?>
