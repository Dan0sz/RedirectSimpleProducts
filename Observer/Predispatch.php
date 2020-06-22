<?php

namespace DaanvdB\RedirectSimpleProducts\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class Predispatch implements ObserverInterface {

    protected $_redirect;
    protected $_productTypeConfigurable;
    protected $_productRepository;
    protected $_storeManager;

    public function __construct (
        \Magento\Framework\App\Response\Http $redirect,
        \Magento\ConfigurableProduct\Model\ResourceModel\Product\Type\Configurable $productTypeConfigurable,
        \Magento\Catalog\Model\ProductRepository $productRepository,
        \Magento\Store\Model\StoreManagerInterface $storeManager
    ) {
        $this->_redirect = $redirect;
        $this->_productTypeConfigurable = $productTypeConfigurable;
        $this->_productRepository = $productRepository;
        $this->_storeManager = $storeManager;
    }

    public function execute(Observer $observer)
    {
        $pathInfo = $observer->getEvent()->getRequest()->getPathInfo();

        /** If it's not a product view we don't need to do anything. */
        if (strpos($pathInfo, 'product') === false) {
            return;
        }

        $request = $observer->getEvent()->getRequest();
        $simpleProductId = $request->getParam('id');
        if (!$simpleProductId) {
            return;
        }

        $simpleProduct = $this->_productRepository->getById($simpleProductId, false, $this->_storeManager->getStore()->getId());
        if (!$simpleProduct || $simpleProduct->getTypeId() != \Magento\Catalog\Model\Product\Type::TYPE_SIMPLE) {
            return;
        }

        $configProductId = $this->_productTypeConfigurable->getParentIdsByChild($simpleProductId);

        if (isset($configProductId[0])) {
            $configProduct = $this->_productRepository->getById($configProductId[0], false, $this->_storeManager->getStore()->getId());
            $configType = $configProduct->getTypeInstance();
            $attributes = $configType->getConfigurableAttributesAsArray($configProduct);

            $options = [];
            foreach ($attributes as $attribute) {
                $id = $attribute['attribute_id'];
                $value = $simpleProduct->getData($attribute['attribute_code']);
                $options[$id] = $value;
            }

            // Pass on any query parameters to the configurable product's URL.
            $query = $request->getQuery() ? '?' . http_build_query($request->getQuery()) : '';

            // Generate hash for selected product options.
            $hash = $options ? '#' . http_build_query($options) : '';

            $configProductUrl = $configProduct->getUrlModel()
                                              ->getUrl($configProduct) . $query . $hash;
            $this->_redirect->setRedirect($configProductUrl, 301);
        }
    }
}
