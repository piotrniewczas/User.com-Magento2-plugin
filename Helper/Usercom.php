<?php
namespace Usercom\Analytics\Helper;

class Usercom extends \Magento\Framework\App\Helper\AbstractHelper
{

    const COOKIE_USERKEY = "userKey";

    protected $helper;
    protected $cookieManager;
    protected $storeManager;
    protected $productRepositoryFactory;
    protected $subscriber;


    public function __construct(
        \Usercom\Analytics\Helper\Data $helper,
        \Magento\Framework\Stdlib\CookieManagerInterface $cookieManager,
        \Magento\Store\Model\StoreManagerInterface $storeManager, 
        \Magento\Catalog\Api\ProductRepositoryInterfaceFactory $productRepositoryFactory,
        \Magento\Newsletter\Model\Subscriber $subscriber,
        \Magento\Framework\App\Helper\Context $context
    ) {
        $this->helper = $helper;
        $this->cookieManager = $cookieManager;
        $this->storeManager = $storeManager;
        $this->productRepositoryFactory = $productRepositoryFactory;
        $this->subscriber= $subscriber;
        parent::__construct($context);
    }

    public function sendPostEvent($url,$data){

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://".$this->helper->getSubdomain()."/api/public/".$url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => array(
                "Accept: */*; version=2",
                "authorization: Token ".$this->helper->getToken(),
                "content-type: application/json"
            ),
        ));

        $response = curl_exec($curl);
        $err = curl_error($curl);

        curl_close($curl);

        $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/Usercom.log');
        $logger = new \Zend\Log\Logger();
        $logger->addWriter($writer);
        $logger->info($url." - ".(($err) ?: $response));

        return ($err) ? null : json_decode($response);
    }    


    public function sendPutEvent($url,$data){

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://".$this->helper->getSubdomain()."/api/public/".$url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "PUT",
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => array(
                "Accept: */*; version=2",
                "authorization: Token ".$this->helper->getToken(),
                "content-type: application/json"
            ),
        ));

        $response = curl_exec($curl);
        $err = curl_error($curl);

        curl_close($curl);

        $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/Usercom.log');
        $logger = new \Zend\Log\Logger();
        $logger->addWriter($writer);
        $logger->info($url." - ".(($err) ?: $response));

        return ($err) ? null : json_decode($response);
    }    


    public function sendGetEvent($url){

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://'.$this->helper->getSubdomain().'/api/public/'.$url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "GET",
            CURLOPT_HTTPHEADER => array(
                "Accept: */*; version=2",
                "authorization: Token ".$this->helper->getToken()
            ),
        ));

        $response = curl_exec($curl);
        $err = curl_error($curl);

        curl_close($curl);

        $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/Usercom.log');
        $logger = new \Zend\Log\Logger();
        $logger->addWriter($writer);
        $logger->info($url." - ".(($err) ?: $response));

        return ($err) ? null : json_decode($response);
    }


    public function getCustomerById($id){

        return $this->sendGetEvent('users/'.$id.'/');
    }


    public function getCustomerByCustomId($custom_id){

        return $this->sendGetEvent('users-by-id/'.$custom_id.'/'); 
    }


    public function findCustomerByEmail($email){

        return $this->sendGetEvent('users/search/?email='.$email); 
    }

    public function createCustomer($data){

        return $this->sendPostEvent("users/", $data);
    }

    public function getProductByCustomId($custom_id){

        return $this->sendGetEvent("products-by-id/$custom_id/details/");
    }

    public function createProduct($data){

        return $this->sendPostEvent("products/",$data);
    }

    public function createProductEvent($id,$data){

        return $this->sendPostEvent("products/$id/product_event/", $data);
    }

    public function findCustomerByUserKey($userKey){

        return $this->sendGetEvent("users/search/?key=$userKey");
    }

    public function getFrontUserKey(){

        return $this->cookieManager->getCookie(self::COOKIE_USERKEY);
    }

    public function createEvent($data){

        if($this->helper->sendStoreSource())
            $data["data"]["store_source"] = $this->storeManager->getStore()->getId();
            

        return $this->sendPostEvent("events/", $data);
    }

    public function updateCustomer($id,$data){

        return $this->sendPutEvent("users/$id/",$data);
    }

    public function getUsercomCustomerId($customerId = null){

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $customerSession = $objectManager->get('Magento\Customer\Model\Session');

        //if not customerId but login
        if($customerId == null && $customerSession->isLoggedIn()) {
            $customerId = $customerSession->getCustomer()->getId();
        }

        if($customerSession->isLoggedIn() || $customerId){
            //if customer exist in user.com 
            if( ($usercomCustomer = $this->getCustomerByCustomId($customerId)) && isset($usercomCustomer->id) )
                return $usercomCustomer->id;
            //else create customer
            else {
                $data = $this->getCustomerData($customerId);
                //if customer created return customer id
                return ( ($usercomCustomer = $this->createCustomer($data)) && isset($usercomCustomer->id) ) ? $usercomCustomer->id : false;
            }
        } else 
            //else return customer by user key if exist
            return ( ($usercomCustomer = $this->findCustomerByUserKey($this->getFrontUserKey())) && isset($usercomCustomer->id) ) ? $usercomCustomer->id : false;
    }

    public function getUsercomProductId ($productId = null) {

        if(!$productId)
            return false;

        if(($usercomProduct = $this->getProductByCustomId($productId)) && isset($usercomProduct->id))
            return $usercomProduct->id;
        else {         
            $productData = $this->getProductData($productId); 
            return ( ($usercomProduct = $this->createProduct($productData)) && isset($usercomProduct->id) ) ? $usercomProduct->id : false;
        }
    }

    public function getCustomerData($customerId = null){

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $customerSession = $objectManager->get('Magento\Customer\Model\Session');

        //if not customerId but login
        if($customerId == null && $customerSession->isLoggedIn()) {
            $customerId = $customerSession->getCustomer()->getId();
        }

        if(!$customerId)
            return;

        $customer = $objectManager->create('Magento\Customer\Model\Customer')->load($customerId);

        return array(
            "custom_id" => $customerId,
            "first_name" => $customer->getFirstName(),
            "last_name" => $customer->getLastName(),
            "email" => $customer->getEmail(),
            "unsubscribed" => !$this->subscriber->loadByCustomerId($customerId)->isSubscribed()
        );
    }

    public function getProductData($productId = null){

        if(!$productId)
            return;

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $product = $objectManager->create('Magento\Catalog\Model\Product')->load($productId);
        $categories = $product->getCategoryIds();
        $categoryName = "";
        foreach($categories as $category){
            $cat = $objectManager->create('Magento\Catalog\Model\Category')->load($category);
            $categoryName .= $cat->getName().", ";
        }
        $categoryName = rtrim($categoryName, ", ");

        $data =  array(
            "custom_id" => $productId,
            "name" => $product->getName(),
            "price" => (float)$product->getFinalPrice(),
            "category_name" => $categoryName, 
            "product_url" => $objectManager->create('Magento\Catalog\Model\Product')->load($productId)->getProductUrl(),
            "image_url" => $this->storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA) . 'catalog/product' . $this->productRepositoryFactory->create()->getById($productId)->getData('image')
        );
        
        $attributes = $product->getAttributes();
        foreach($attributes as $a){
            $value = $product->getData($a->getName());
            if($value != null){
                $value = ( gettype($value) == "object" || gettype($value) == "array" ) ? json_encode($value) : strval($value); 
                $data[$a->getName()] = $value;
            }
        }

        return $data;
    }

}
