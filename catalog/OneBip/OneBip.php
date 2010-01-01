<?php
  /*
   * OneBip Payment System 
   * 
   * Developed by Dynamo Effects
   * Copyright 2007 OneBip
   *
   * Released under the GNU General Public License Version 2
   */
   
  /*
   * Defines
   */
  define('FILENAME_ONEBIP_DIR', 'OneBip/');
  define('ONEBIP_IPN_SERVER', '81.29.212.100');

  class OneBip {
    var $isAdmin = false;
    var $downloadController = false;
    var $orderDetails = array();

    /* Initializes the class */
    function OneBip() {

      /* Determine if the administration section is calling this script */
      if (strpos($_SERVER['SCRIPT_FILENAME'], DIR_FS_ADMIN) !== false) {
        $this->isAdmin = true;
      }
      
      /* Check if Linda McGrath's Downloads Controller is installed */
      if (file_exists(DIR_WS_FUNCTIONS . 'downloads_controller.php')) {
        $this->downloadController = true;
      }

      if (count($_GET) || count($_POST)) {
        $this->HandleRequest();
      }
    }
    
    /* Handles any OneBip specific GET and POST variables */
    function HandleRequest() {
      global $cart, $currencies;
      
      /* Handle Store admin requests */
      if ($this->isAdmin) {
        /* 
         * Order page details
         */
        if (strstr($_SERVER['PHP_SELF'], DIR_WS_ADMIN . FILENAME_ORDERS) && (int)$_GET['oID'] > 0) {
          $this->orderDetails = array();
          
          $order_query = tep_db_query("SELECT onebip_details" .
                                      " FROM " . TABLE_ORDERS .
                                      " WHERE orders_id = " . (int)$_GET['oID'] .
                                      " LIMIT 1");
                                      
          if (tep_db_num_rows($order_query)) {
            $order_details = tep_db_fetch_array($order_query);
            
            $order_details = explode('&', $order_details['onebip_details']);

            foreach ($order_details as $od) {
              $detail = explode('=', $od);
              
              if (count($detail) == 2) {
                $this->orderDetails[$detail[0]] = $detail[1];
              }
            }
          }
        }
      /* Handle public requests */
      } else {
        /*
         * If the customer is purchasing a product with OneBip
         */
        if ($_GET['action'] == 'add_product' && (array_key_exists('OneBipBuyNow', $_POST) || array_key_exists('OneBipBuyNow_x', $_POST)) && $this->ProductStatus($_POST['products_id'])) {
          /* We need to process the order before going to OneBip */
          tep_redirect(tep_href_link(FILENAME_ONEBIP_DIR . FILENAME_CHECKOUT_PROCESS, 'products_id=' . urlencode(tep_get_uprid($_POST['products_id'], $_POST['id'])), 'SSL'));       
        
        /* This section will execute when a customer returns from OneBip and hits the checkout_success page */
        } elseif (strstr($_SERVER['PHP_SELF'], FILENAME_CHECKOUT_SUCCESS) && strlen($_GET['u']) == 30) {
          $orderUnique = preg_replace('/[^0-9a-zA-Z]/', '', $_GET['u']);
          
          $order_query = tep_db_query("SELECT orders_id as id, orders_status as status" . 
                                      " FROM " . TABLE_ORDERS . 
                                      " WHERE onebip_unique = '" . $orderUnique . "'" .
                                      " LIMIT 1");
        
          if (tep_db_num_rows($order_query) > 0) {
            $order = tep_db_fetch_array($order_query);

            //Update the order status if the payment hasn't already been confirmed
            if ($order['status'] == ONEBIP_ORDER_STATUS_PENDING_1) {
              $this->SendOrderEmail($order['id']);
              
              tep_db_query("UPDATE " . TABLE_ORDERS . 
                           " SET orders_status = " . ONEBIP_ORDER_STATUS_PENDING_2 .
                           " WHERE onebip_unique = '" . $orderUnique . "'" .
                           " LIMIT 1");
                           
              $sql_data_array = array('orders_id' => $order['id'], 
                                      'orders_status_id' => ONEBIP_ORDER_STATUS_PENDING_2, 
                                      'date_added' => 'now()', 
                                      'customer_notified' => 0,
                                      'comments' => ONEBIP_ORDER_STATUS_PENDING_2_COMMENT);
                                      
              tep_db_perform(TABLE_ORDERS_STATUS_HISTORY, $sql_data_array);
            }
          }
        
        /* Capture IPN Responses */
        } elseif ($_GET['payment_id'] != '' && strlen($_GET['u']) == 30) {
          if ($_SERVER['REMOTE_ADDR'] == ONEBIP_IPN_SERVER) {
            $orderUnique = preg_replace('/[^0-9a-zA-Z]/', '', $_GET['u']);
            
            $order_check = tep_db_query("SELECT orders_id as id, orders_status" .
                                        " FROM " . TABLE_ORDERS . 
                                        " WHERE onebip_unique = '" . $orderUnique . "'" .
                                        "   AND orders_status != " . ONEBIP_ORDER_STATUS_PAYMENT_CONFIRMATION .
                                        " LIMIT 1");
          
            if (tep_db_num_rows($order_check) > 0) {
              //Add details to order comments
              $order = tep_db_fetch_array($order_check);
              
              if ($order['orders_status'] == ONEBIP_ORDER_STATUS_PENDING_1) {
                $this->SendOrderEmail($order['id']);
              }
              
              tep_db_query("UPDATE " . TABLE_ORDERS . 
                           " SET orders_status = " . ONEBIP_ORDER_STATUS_PAYMENT_CONFIRMATION .
                           "    ,onebip_details = '" . tep_db_prepare_input($_SERVER['QUERY_STRING']) . "'" .
                           " WHERE orders_id = '" . $order['id'] . "'" .
                           " LIMIT 1");
              
              $sql_data_array = array('orders_id' => $order['id'], 
                                      'orders_status_id' => ONEBIP_ORDER_STATUS_PAYMENT_CONFIRMATION, 
                                      'date_added' => 'now()', 
                                      'customer_notified' => 1,
                                      'comments' => ONEBIP_ORDER_STATUS_PAYMENT_CONFIRMATION_COMMENT . "\n\n" .
                                                    "OneBip Payment ID: " . tep_db_prepare_input($_GET['payment_id']) . "\n" .
                                                    "Enduser Cost: " . tep_db_prepare_input(number_format($_GET['endUserCost'] / 100, 2) . ' ' . $_GET['ccy']) . " (VAT inc)"
                                      );
                                      
              tep_db_perform(TABLE_ORDERS_STATUS_HISTORY, $sql_data_array);
              
              /* Activate the download if necessary */
              $download_query = tep_db_query("SELECT orders_id," .
                                             "       orders_products_id," .
                                             "       orders_products_filename," .
                                             "       download_maxdays," .
                                             "       download_count" .
                                             " FROM " . TABLE_ORDERS_PRODUCTS_DOWNLOAD . "_onebip" .
                                             " WHERE orders_id = " . (int)$order['id']);
                                             
              if (tep_db_num_rows($download_query) > 0) {
                while ($download = tep_db_fetch_array($download_query)) {
                  $sql_data_array = array('orders_id' => (int)$download['orders_id'], 
                                          'orders_products_id' => $download['orders_products_id'], 
                                          'orders_products_filename' => $download['orders_products_filename'], 
                                          'download_maxdays' => $download['download_maxdays'], 
                                          'download_count' =>  $download['download_count']);
                  tep_db_perform(TABLE_ORDERS_PRODUCTS_DOWNLOAD, $sql_data_array);
                }
                
                /* Delete all pending downloads */
                tep_db_query("DELETE FROM " . TABLE_ORDERS_PRODUCTS_DOWNLOAD . "_onebip WHERE orders_id = " . $order['id']);
                
                if ($order['orders_status'] == ONEBIP_ORDER_STATUS_PENDING_1) {
                  $this->SendDownloadEmail($order['id']);
                }
              }
              //Display "OK" so that OneBip knows the transaction was successful
              die('OK');
            } else {
              die('FAIL: Order not found or was already completed');
            }
          } else {
            die('FAIL: IP Address Invalid');
          }
        }
      }
    }
    
    function SaveSettings($pID) {
      if ($this->CleanInteger($pID) > 0) {
        $OBStatus = ($_POST['products_onebip_status'] == '1' ? 1 : 0);
        $OBID = $this->CleanInteger($_POST['products_onebip_id']);
        
        /* Sanity Check */
        if ($OBStatus == 0) {
          $OBID = 0;
        } elseif ($OBID <= 0) {
          $OBStatus = 0;
          $OBID = 0;
        }

        tep_db_query("UPDATE " . TABLE_PRODUCTS . 
                     " SET products_onebip_status = " . $OBStatus . "," .
                     "     products_onebip_id = " . $OBID . 
                     " WHERE products_id = " . $pID . 
                     " LIMIT 1");
              
      }
    }
    
    function SendOrderEmail($orderID) {
      global $currencies;
      
      $orderID = $this->CleanInteger($orderID);
      
      if ($orderID < 1) return false;
      
      $order_query = tep_db_query("SELECT customers_name," .
                                  "       customers_email_address" .
                                  " FROM " . TABLE_ORDERS . 
                                  " WHERE orders_id = " . (int)$orderID . 
                                  " LIMIT 1");
                                  
      if (tep_db_num_rows($order_query) > 0) {
        $order = tep_db_fetch_array($order_query);
        
        $email_order = STORE_NAME . "\n" . 
                       ONEBIP_ORDER_EMAIL_SEPARATOR . "\n" . 
                       ONEBIP_ORDER_EMAIL_TEXT_ORDER_NUMBER . ' ' . $orderID . "\n" .
                       ONEBIP_ORDER_EMAIL_TEXT_INVOICE_URL . ' ' . tep_href_link(FILENAME_ACCOUNT_HISTORY_INFO, 'order_id=' . $orderID, 'SSL', false) . "\n" .
                       ONEBIP_ORDER_EMAIL_TEXT_DATE_ORDERED . ' ' . strftime(DATE_FORMAT_LONG) . "\n\n";

        $email_order .= ONEBIP_ORDER_EMAIL_TEXT_PRODUCTS . "\n" . 
                        ONEBIP_ORDER_EMAIL_SEPARATOR . "\n";

        $order_product_query = tep_db_query("SELECT orders_products_id, products_model, products_name, final_price" .
                                            " FROM " . TABLE_ORDERS_PRODUCTS . 
                                            " WHERE orders_id = " . (int)$orderID);
        $total = 0;
        while ($op = tep_db_fetch_array($order_product_query)) {
          $email_order .= '1 x ' . $op['products_name'] . ' (' . $op['products_model'] . ') = ' . $currencies->display_price($op['final_price'], 0, 1);
          
          $total += $op['final_price'];
          
          $attributes_query = tep_db_query("SELECT products_options, products_options_values" .
                                           " FROM " . TABLE_ORDERS_PRODUCTS_ATTRIBUTES .
                                           " WHERE orders_products_id = " . $op['orders_products_id'] . " 
                                               AND orders_id = " . (int)$orderID);
                                               
          if (tep_db_num_rows($attributes_query) > 0) {
            while ($attribute = tep_db_fetch_array($attributes_query)) {
              $email_order .= "\n\t" . $attribute['products_options'] . ' ' . $attribute['products_options_values'];
            }
          }
        }
        
        $email_order .= "\n" . ONEBIP_ORDER_EMAIL_SEPARATOR . "\n";
        
        $orders_total_query = tep_db_query("SELECT title, text" . 
                                           " FROM " . TABLE_ORDERS_TOTAL .
                                           " WHERE orders_id = " . (int)$orderID .
                                           " LIMIT 1");
                                           
        $orders_total = tep_db_fetch_array($orders_total_query);

        $email_order .= strip_tags($orders_total['title']) . ' ' . strip_tags($orders_total['text']) . "\n";

        tep_mail($order['customers_name'], $order['customers_email_address'], ONEBIP_ORDER_EMAIL_TEXT_SUBJECT, $email_order, STORE_OWNER, STORE_OWNER_EMAIL_ADDRESS);

        if (SEND_EXTRA_ORDER_EMAILS_TO != '') {
          tep_mail('', SEND_EXTRA_ORDER_EMAILS_TO, ONEBIP_ORDER_EMAIL_TEXT_SUBJECT, $email_order, STORE_OWNER, STORE_OWNER_EMAIL_ADDRESS);
        }
        
        return true;
      }
      
      return false;
    }
    
    function SendDownloadEmail($orderID) {
      $orderID = $this->CleanInteger($orderID);
      
      $order_query = tep_db_query("SELECT customers_name," .
                                  "       customers_email_address" .
                                  " FROM " . TABLE_ORDERS . 
                                  " WHERE orders_id = " . (int)$orderID . 
                                  " LIMIT 1");
                                  
      if (tep_db_num_rows($order_query) > 0) {
        $order = tep_db_fetch_array($order_query);
        
        $email_order  = ONEBIP_DOWNLOAD_EMAIL_TEXT_DOWNLOAD_READY . "\n\n";
        $email_order .= tep_href_link(FILENAME_ACCOUNT_HISTORY_INFO, 'order_id=' . $orderID, 'SSL', false);

        tep_mail($order['customers_name'], $order['customers_email_address'], ONEBIP_DOWNLOAD_EMAIL_TEXT_SUBJECT, $email_order, STORE_OWNER, STORE_OWNER_EMAIL_ADDRESS);
      }
    }
    
    /* Return if this product is setup to allow OneBip purchasing */
    function ProductStatus($pID) {
      /* Clean the incoming variable */
      $pID = tep_get_prid($pID);

      if ($pID > 0) {
        //Make sure that this is a valid product and set to allow OneBip purchases
        $product_check_query = tep_db_query("SELECT products_onebip_status" .
                                            " FROM " . TABLE_PRODUCTS . 
                                            " WHERE products_id = " . $pID . 
                                            "   AND products_onebip_status = 1" .
                                            "   AND products_status = 1" .
                                            " LIMIT 1");
                                            
        if (tep_db_num_rows($product_check_query) == 1) {
          return true;
        }
      }
      
      /* Return false if all else fails */
      return false;
    }
    
    function ProductPrice($pID) {
      /* Clean the incoming variable */
      $productID = $this->CleanInteger(tep_get_prid($pID));

      if ($productID > 0) {
        $price_query = tep_db_query("SELECT products_price" . 
                                    " FROM " . TABLE_PRODUCTS . 
                                    " WHERE products_id = " . (int)$productID . 
                                    " LIMIT 1");
                                    
        if (tep_db_num_rows($price_query) < 1) return false;
        
        $price = tep_db_fetch_array($price_query);
        
        return $price['products_price'];
        
        /* NOTE: Attribute prices aren't included 
         * because OneBip only supports specific 
         * price points at this time.
         */
      }
      
      return false;
    }
    
    /* Retrieve the OneBip item ID for this product */
    function ProductOneBipID($pID) {
      /* Clean the incoming variable */
      $pID = tep_get_prid($pID);

      if ($pID > 0) {
        $productID_query = tep_db_query("SELECT products_onebip_status as status," . 
                                        "       products_onebip_id as id" . 
                                        " FROM " . TABLE_PRODUCTS . 
                                        " WHERE products_id = " . $pID . 
                                        " LIMIT 1");
                                        
        if (tep_db_num_rows($productID_query) > 0) {
          $productID = tep_db_fetch_array($productID_query);
          
          /* If this product is marked as a OneBip product, return true */
          if ($productID['id'] > 0 && $productID['status'] == '1') {
            return $productID['id'];
          }
        }
      }
      
      /* Return false if all else fails */
      return false;
    }

    function CanDownload($orderUnique) {
      $orderUnique = preg_replace('/[^0-9a-zA-Z]/', '', $orderUnique);
      
      if ($orderUnique != '') {
        $order_query = tep_db_query("SELECT order_id" .
                                    " FROM " . TABLE_ORDERS . 
                                    " WHERE onebip_unique = '" . $orderUnique . "'" .
                                    " LIMIT 1");
                                    
        if (tep_db_num_rows($order_query) > 0) {
          return true;
        }
      }
      
      return false;
    }
    
    function CleanInteger($int) {
      return preg_replace('/[^0-9]/', '', $int);
    }
    
    function ProductWeight($pID) {
      $pID = tep_get_prid($pID);
      
      if ($pID > 0) {
        $weight_query = tep_db_query("SELECT products_weight as weight" .
                                     " FROM " . TABLE_PRODUCTS . 
                                     " WHERE products_id = " . (int)$pID . 
                                     " LIMIT 1");
        if (tep_db_num_rows($weight_query) > 0) {
          $weight = tep_db_fetch_array($weight_query);
          
          return $weight['weight'];
        }
      }
      
      return false;
    }
       
    function ProductType($pID) {
      $pType = 'physical';
      
      if (DOWNLOAD_ENABLED == 'true') {

        $productID = $this->CleanInteger(tep_get_prid($pID));
        
        $options = $this->UnserializeAttributes($pID);
        
        if (count($options) > 0) {
          foreach ($options as $optionID => $attID) {
            $virtual_check_query = tep_db_query("SELECT products_attributes_filename" . 
                                                " FROM " . TABLE_PRODUCTS_ATTRIBUTES_DOWNLOAD . " pad" .
                                                "   LEFT JOIN " . TABLE_PRODUCTS_ATTRIBUTES . " pa" .
                                                "     ON (pad.products_attributes_id = pa.products_attributes_id)" . 
                                                " WHERE pa.products_id = " . (int)$productID . 
                                                "   AND pa.options_id = " . (int)$optionID . 
                                                "   AND pa.options_values_id = " . (int)$attID . 
                                                " LIMIT 1");
            if (tep_db_num_rows($virtual_check_query) > 0) {
              $pType = 'virtual';
            }
          }
        }
      }
      
      return $pType;
    }
    
    function UnserializeAttributes($pID) {
      if (strpos($pID, '{') !== false && strpos($pID, '}') !== false) {
        $productID = $this->CleanInteger(tep_get_prid($pID));
        
        $productOptions = substr($pID, strlen($productID) + 1);
        
        $productOptions = explode('{', $productOptions);
        
        $options = array();
        
        foreach ($productOptions as $productOption) {
          $opt = explode('}', $productOption);

          $opt[0] = $this->CleanInteger($opt[0]);
          $opt[1] = $this->CleanInteger($opt[1]);
          
          $options[$opt[0]] = $opt[1];
        }
        
        return $options;
      }
      
      return array();
    }
    
  }
  
  $language_file = $language;
  
  /* Default to English if the selected language isn't available */
  if (!file_exists('languages/' . $language_file . '.php')) {
    $language_file = 'english';
  }
  
  /* application_top.php needs to be called if this script is being called specifically (for IPN) */
  if (!defined('STORE_NAME')) {
    chdir('..');
  
    include('includes/application_top.php');
  }
  
  /* If OneBip is enabled, execute the class */
  if (ONEBIP_STATUS == 'Enabled') {
    /* Load the language file */
    require('languages/' . $language_file . '.php');
     
    $OneBip = new OneBip;
  }
?>