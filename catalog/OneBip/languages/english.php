<?php
  /*
   * OneBip Payment System 
   * 
   * Developed by Dynamo Effects
   * Copyright 2007 OneBip
   *
   * Released under the GNU General Public License Version 2
   */
  
  /* Admin Variables */
  define('ONEBIP_ADMIN_CONFIG_TITLE', 'OneBip Product Configuration');
  define('ONEBIP_ADMIN_CONFIG_STATUS', 'This item can be purchased using OneBip.');
  define('ONEBIP_ADMIN_CONFIG_ID', 'OneBip Item ID:');
  define('ONEBIP_ADMIN_CONFIG_ID_HELP', '<small>(i.e. 12345)</small>');
  define('ONEBIP_ADMIN_CONFIG_TIP', '<b>TIP:</b> To update or view your available OneBip items,<br />visit your control panel by clicking here:');
  define('ONEBIP_ADMIN_CONTROL_PANEL_TEXT', 'OneBip Control Panel');
  
  define('ONEBIP_ADMIN_ORDER_DETAILS_HEADER', 'OneBip Order Details');
  
  /* Public Variables */
  define('ONEBIP_BUTTON_TEXT', '<b>Pay instantly with your<br />cell phone number!</b>');
  define('ONEBIP_ORDER_STATUS_PENDING_1_COMMENT', 'Payment has not yet been made.');
  define('ONEBIP_ORDER_STATUS_PENDING_2_COMMENT', 'Customer has returned from OneBip but payment has not been confirmed yet.  This can take up to 72 hours depending on the customer\'s cell phone status.');
  define('ONEBIP_ORDER_STATUS_PAYMENT_CONFIRMATION_COMMENT', 'Payment has been confirmed.');

  define('ONEBIP_DOWNLOAD_EMAIL_TEXT_SUBJECT', STORE_NAME . ': Product Download Instructions');

  define('ONEBIP_DOWNLOAD_EMAIL_TEXT_DOWNLOAD_READY', "Dear Customer:\n" .
                                             "Your OneBip payment has been confirmed and your download has been made available.  Please visit your order history page to download the product(s) that you ordered.");
  
  define('ONEBIP_DOWNLOAD_EMAIL_TEXT_ORDER_NUMBER', 'Order Number:');
  define('ONEBIP_DOWNLOAD_EMAIL_TEXT_INVOICE_URL', 'Detailed Invoice:');
  
  define('ONEBIP_ORDER_EMAIL_TEXT_SUBJECT', 'Order Process');
  define('ONEBIP_ORDER_EMAIL_TEXT_ORDER_NUMBER', 'Order Number:');
  define('ONEBIP_ORDER_EMAIL_TEXT_INVOICE_URL', 'Detailed Invoice:');
  define('ONEBIP_ORDER_EMAIL_TEXT_DATE_ORDERED', 'Date Ordered:');
  define('ONEBIP_ORDER_EMAIL_TEXT_PRODUCTS', 'Products');

  define('ONEBIP_ORDER_EMAIL_TEXT_TOTAL', 'Total:    ');

  define('ONEBIP_ORDER_EMAIL_SEPARATOR', '------------------------------------------------------');
?>