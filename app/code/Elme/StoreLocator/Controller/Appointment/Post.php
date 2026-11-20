<?php

declare(strict_types=1);

namespace Elme\StoreLocator\Controller\Appointment;

use Elme\StoreLocator\Model\Appointment\Mail;
use Elme\StoreLocator\Model\Appointment\Validator;
use Elme\StoreLocator\ViewModel\AppointmentForm;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Message\ManagerInterface as MessageManagerInterface;
use Magento\Framework\UrlInterface;
use Psr\Log\LoggerInterface;

/**
 * FE-90: accept a showroom appointment request from a logged-in trade customer and email the showroom team.
 * Form key is checked by the default CSRF validator (the form block is not cached).
 */
class Post implements HttpPostActionInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly RedirectFactory $resultRedirectFactory,
        private readonly MessageManagerInterface $messageManager,
        private readonly DataPersistorInterface $dataPersistor,
        private readonly CustomerSession $customerSession,
        private readonly AppointmentForm $form,
        private readonly Validator $validator,
        private readonly Mail $mail,
        private readonly UrlInterface $urlBuilder,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(): Redirect
    {
        $redirect = $this->resultRedirectFactory->create();
        if (!$this->customerSession->isLoggedIn()) {
            $this->messageManager->addNoticeMessage(__('Log in to your trade account to book a showroom appointment.'));

            return $redirect->setUrl($this->form->getLoginUrl());
        }

        $params = $this->request->getParams();
        try {
            $request = $this->validator->validate($params, $this->form->getShowrooms(), $this->form->today());
            $request['account_id'] = (int) $this->customerSession->getCustomerId();
            $this->mail->send($request);

            $this->dataPersistor->clear(AppointmentForm::PERSIST_KEY);
            $this->dataPersistor->set(AppointmentForm::PERSIST_SENT_KEY, $request);

            return $redirect->setUrl($this->pageUrl());
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Exception $e) {
            $this->logger->critical($e);
            $this->messageManager->addErrorMessage(__(
                'We could not send your request. Please try again, or call the showroom.'
            ));
        }

        unset($params['form_key'], $params['hideit']);
        $this->dataPersistor->set(AppointmentForm::PERSIST_KEY, $params);

        return $redirect->setUrl($this->pageUrl());
    }

    private function pageUrl(): string
    {
        return $this->urlBuilder->getDirectUrl(AppointmentForm::PAGE_PATH, [
            '_nosid' => true,
            '_fragment' => 'elme-appointment',
        ]);
    }
}
