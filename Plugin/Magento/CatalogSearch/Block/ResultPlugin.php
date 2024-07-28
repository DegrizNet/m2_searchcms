<?php
namespace Magelan\SearchCms\Plugin\Magento\CatalogSearch\Block;

use Magento\CatalogSearch\Block\Result as Subject;
use Magento\Framework\View\Layout;
use Magento\Framework\App\Request\Http as Request;

use Closure;

class ResultPlugin
{
    const MAGENTO_QUERY_VAR = 'q';

    protected $layout;
    protected $request;
    protected $cmsPagesBlock;

    public function __construct(
        Layout $layout,
        Request $request,
        \Magelan\SearchCms\Block\SearchResults $cmsPagesBlock
    ) {
        $this->layout = $layout;
        $this->request = $request;
        $this->cmsPagesBlock = $cmsPagesBlock;
    }

   public function aroundGetProductListHtml(Subject $result, Closure $callback)
    {
        try {
            $searchTerm = trim($this->request->getParam(self::MAGENTO_QUERY_VAR));
            $this->cmsPagesBlock->setSearchTerm($searchTerm);

            $productListHtml = $callback();
            $cmsPagesListHtml = $this->getCmsPagesListHtml();

            // Insert CMS pages search results into the product search results block
            $productListHtml .= $cmsPagesListHtml;

            return $productListHtml;
        } catch (\Exception $e) {
            // Handle exceptions if needed
        }

        return isset($productListHtml) ? $productListHtml : '';
    }

    protected function getCmsPagesListHtml()
    {
        $searchTerm = trim($this->request->getParam(self::MAGENTO_QUERY_VAR));

        if (empty($searchTerm)) {
            return '';
        }

        // Implement your logic to fetch and format CMS pages based on the search term using the ListCmsPages block
        $this->cmsPagesBlock->setSearchTerm($searchTerm);

        return $this->cmsPagesBlock->toHtml();
    }
}