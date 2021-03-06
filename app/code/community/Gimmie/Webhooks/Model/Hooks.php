<?php
class Gimmie_Webhooks_Model_Hooks {

  /**
   * Query all apps in database and get header block from app too
   * append to head block
   *
   */
  public function injectScripts(Varien_Event_Observer $observer = null) {
    $layout = $observer->getEvent()->getLayout();
    $head = $layout->getBlock("head");

    if (!is_object($head)) {
      return;
    }

    $applications = $this->_getEnabledApps();
    foreach($applications as $application) {
      $scripts = $application->getScripts();
      $block = $layout->createBlock(
        "Mage_Core_Block_Template",
        "gimmie_scripts",
        array("template" => "gimmie/scripts.phtml")
      )->setData('scripts', $scripts);
      $head->append($block);
    }
  }

  public function dispatchRegisterSuccess(Varien_Event_Observer $observer = null) {
    $urls = $this->_getEventUrls('register');
    if (count($urls) === 0) {
      return;
    }

    $data = $this->_getBaseData($observer);

    $customer = $observer->getCustomer();
    $data["user"] = array(
      "id" => $customer->getId(),
      "name" => $customer->getName(),
      "email" => $customer->getEmail()
    );

    $helper = Mage::helper('gimmie_webhooks');
    foreach($urls as $url) {
      $helper->send($url, $data);
    }
  }

  public function dispatchLoginSuccess(Varien_Event_Observer $observer = null) {
    $urls = $this->_getEventUrls('login');
    if (count($urls) === 0) {
      return;
    }

    $data = $this->_getBaseData($observer);
    $helper = Mage::helper('gimmie_webhooks');
    foreach($urls as $url) {
      $helper->send($url, $data);
    }
  }

  public function dispatchViewItem(Varien_Event_Observer $observer = null) {
    $urls = $this->_getEventUrls('viewProduct');
    if (count($urls) === 0) {
      return;
    }

    $helper = Mage::helper('gimmie_webhooks');
    $product = $observer->getEvent()->getProduct();
    $data = $this->_getBaseData($observer);
    $data["product"] = $helper->prepareProductArray($product);

    foreach($urls as $url) {
      $helper->send($url, $data);
    }
  }

  public function dispatchUpdateCart(Varien_Event_Observer $observer = null) {
    $urls = $this->_getEventUrls('updateCart');
    if (count($urls) === 0) {
      return;
    }

    $helper = Mage::helper('gimmie_webhooks');
    $cart = Mage::getModel('checkout/cart');
    $quote = $cart->getQuote();
    $quote->collectTotals();

    $items = array();
    foreach($quote->getAllVisibleItems() as $item) {
      $product = $item->getProduct();
      array_push($items, $helper->prepareProductArray($product));
    }

    $totals = array();
    foreach($quote->getTotals() as $key => $value) {
      $totals[$key] = $value->getData()['value'];
    }

    $data = $this->_getBaseData($observer);
    $data["cart"] = array(
      "totals" => $totals,
      "items" => $items
    );

    foreach($urls as $url) {
      $helper->send($url, $data);
    }
  }

  public function dispatchCheckoutItem(Varien_Event_Observer $observer = null) {
    $urls = $this->_getEventUrls('checkout');
    if (count($urls) === 0) {
        return;
    }

    $helper = Mage::helper('gimmie_webhooks');

    $orderId = $observer->getEvent()->getOrderIds()[0];

    $order = Mage::getModel('sales/order');
    $order->load($orderId);

    $data = $this->_getBaseData($observer);

    $data["orderId"] = $order->getIncrementId();

    $data["order"] = array(
        "hasInvoices" => (bool) $order->hasInvoices(),
        "hasShipments" => (bool) $order->hasShipments()
    );

    foreach($urls as $url) {
      $helper->send($url, $data);
    }
  }

  public function dispatchPaidItem(Varien_Event_Observer $observer = null) {
    $urls = $this->_getEventUrls('paid');
    if (count($urls) === 0) {
      return;
    }

    $payment = $observer->getEvent()->getPayment();
    $order = $payment->getOrder();

    $data = $this->_getBaseData($observer);
    if (!$order->getCustomerIsGuest()) {
      $data["user"] = array(
        "id" => $order->getCustomerId(),
        "name" => $order->getCustomerName(),
        "email" => $order->getCustomerEmail()
      );
    }

    $data["customer"] = array(
      "name" => $order->getCustomerName(),
      "email" => $order->getCustomerEmail(),
      "birth" => Mage::getModel('core/date')->date(DATE_W3C, $order->getCustomerDob())
    );
    $data["order"] = array(
        "hasInvoices" => (bool) $order->hasInvoices(),
        "hasShipments" => (bool) $order->hasShipments()
    );

    $helper = Mage::helper('gimmie_webhooks');
    foreach($urls as $url) {
      $helper->send($url, $data);
    }
  }

  private function _getBaseData($observer) {
    $session = Mage::getSingleton('customer/session');
    $base = array( "session" => $session->getSessionId() );

    if ($session->isLoggedIn()) {
      $customer = $session->getCustomer();
      $user = array(
        "id" => $customer->getId(),
        "name" => $customer->getName(),
        "email" => $customer["email"]
      );
      $base["user"] = $user;
    }
    return $base;
  }

  private function _getEnabledApps() {
    $applications = Mage::getModel('webhooks/application')->getCollection();
    $applications->addFilter('enable', true);
    return $applications;
  }

  private function _getEventUrls($name) {
    $urls = array();
    $applications = $this->_getEnabledApps();
    foreach($applications as $application) {
      $events = json_decode($application->getEvents(), true);
      if (array_key_exists($name, $events)) {
        array_push($urls, $events[$name]);
      }
    }
    return $urls;
  }

}
?>
