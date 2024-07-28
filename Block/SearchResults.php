<?php
namespace Magelan\SearchCms\Block;

use Magento\Framework\View\Element\Template;
use Magento\Cms\Model\ResourceModel\Page\CollectionFactory;
use Magento\UrlRewrite\Model\UrlFinderInterface; 
use Magento\Framework\App\Request\Http;
use Magelan\SearchCms\Helper\Data;

class SearchResults extends Template
{
    protected $request;
    protected $cmsPageCollectionFactory;

    public function __construct(
        Template\Context $context,
        Http $request,
        CollectionFactory $cmsPageCollectionFactory,
        UrlFinderInterface $urlFinder,
        Data $helper,
        array $data = []
    ) {
        $this->request = $request;
        $this->cmsPageCollectionFactory = $cmsPageCollectionFactory;
        $this->urlFinder = $urlFinder;
        $this->helper = $helper;
        parent::__construct($context, $data);
    }

    public function isModuleEnabled()
    {
        return $this->helper->isModuleEnabled();
    }

    public function getSearchQuery()
    {
        return $this->request->getParam('q');
    }

    protected $searchTerm;

    public function setSearchTerm($searchTerm)
    {
        $this->searchTerm = $searchTerm;
        return $this;
    }

    public function getCmsPages()
    {
        $searchQuery = $this->getSearchQuery();
        $searchTerm = $this->searchTerm ?: $searchQuery;

        // Split the search query into individual words
        $searchTerms = explode(' ', $searchTerm);

        // Create a CMS page collection
        $cmsPageCollection = $this->cmsPageCollectionFactory->create();

        // Create an array to store the OR conditions for filtering
        $orConditions = [];

        foreach ($searchTerms as $term) {
            $orConditions[] = [
                'like' => '%' . $term . '%'
            ];
        }

        // Apply filters to search by title or content for any of the terms
        $cmsPageCollection->addFieldToFilter(
            [
                'title',
                'content'
            ],
            $orConditions
        );

        // You can further customize the collection, e.g., sorting, limiting results, etc.

        return $cmsPageCollection;
    }

    public function getCmsResults()
    {
        return $this->getCmsPages();
    }


    public function getCmsPageUrl($pageIdentifier)
    {
        $rewrite = $this->urlFinder->findOneByData([
            'request_path' => $pageIdentifier,
            'store_id' => $this->_storeManager->getStore()->getId()
        ]);
        if ($rewrite && $rewrite->getRequestPath()) {
            return $this->_storeManager->getStore()->getBaseUrl() . $rewrite->getRequestPath();
        } else {
            // If no URL rewrite is found, you may use a default URL or other logic.
            return $this->_storeManager->getStore()->getBaseUrl() . 'cms/page/view/page_id/' . $pageIdentifier;
        }
    }

}