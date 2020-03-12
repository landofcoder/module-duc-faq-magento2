<?php


namespace Ducpa23\FAQ\Controller\Adminhtml\Index;

/**
 * Class Index
 *
 * @package Ducpa23\FAQ\Controller\Index
 */
class Index extends \Magento\Backend\App\Action
{

    protected $resultPageFactory;

    /**
     * Constructor
     *
     * @param \Magento\Framework\App\Action\Context  $context
     * @param \Magento\Framework\View\Result\PageFactory $resultPageFactory
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\View\Result\PageFactory $resultPageFactory
    ) {
        $this->resultPageFactory = $resultPageFactory;
        parent::__construct($context);
    }

    /**
     * Execute view action
     *
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('Ducpa23_FAQ::moduleFAQ');
        $resultPage->getConfig()->getTitle()->prepend((__('FAQ Manager')));

        return $resultPage;
    }
}

