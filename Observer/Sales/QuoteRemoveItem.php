<?php                                                         

namespace Usercom\Analytics\Observer\Sales;                        

class QuoteRemoveItem implements \Magento\Framework\Event\ObserverInterface                                
{

    protected $helper;
    protected $usercom;

    public function __construct(
        \Usercom\Analytics\Helper\Usercom $usercom,
        \Usercom\Analytics\Helper\Data $helper
    ){
        $this->usercom = $usercom;
        $this->helper = $helper;
    }

    public function execute(
        \Magento\Framework\Event\Observer $observer
    ) {
        $productId = $observer->getQuoteItem()->getProduct()->getId();
        
        if( !$this->helper->isModuleEnabled() || !($usercomCustomerId = $this->usercom->getUsercomCustomerId()) || !($usercomProductId = $this->usercom->getUsercomProductId($productId)) )
            return;

        $this->usercom->createProductEvent($usercomProductId,array(
            "id" => $usercomProductId,
            "user_custom_id" => $this->usercom->getCustomerData()["custom_id"],
            "user_id" => $usercomCustomerId,
            "data" => $this->usercom->getProductData($productId),
            "event_type" => "remove",
            "timestamp" => time()
        ));
    }
}
