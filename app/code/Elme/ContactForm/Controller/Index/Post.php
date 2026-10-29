<?php

declare(strict_types=1);

namespace Elme\ContactForm\Controller\Index;

use Elme\ContactForm\Model\Mail;
use Elme\ContactForm\ViewModel\FormData;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Message\ManagerInterface as MessageManagerInterface;
use Magento\Framework\UrlInterface;
use Magento\Newsletter\Model\SubscriptionManagerInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * FE-46: accept the trade Contact Us form, email the store contact, optional newsletter opt-in.
 */
class Post implements HttpPostActionInterface, CsrfAwareActionInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly RedirectFactory $resultRedirectFactory,
        private readonly MessageManagerInterface $messageManager,
        private readonly DataPersistorInterface $dataPersistor,
        private readonly Mail $mail,
        private readonly FormData $formData,
        private readonly SubscriptionManagerInterface $subscriptionManager,
        private readonly StoreManagerInterface $storeManager,
        private readonly UrlInterface $urlBuilder,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(): Redirect
    {
        $redirect = $this->resultRedirectFactory->create();
        if (!$this->request->isPost()) {
            return $redirect->setUrl($this->contactUsUrl());
        }

        try {
            $payload = $this->validatedParams();
            $this->mail->send($payload['email'], ['data' => new DataObject($payload)]);

            if (!empty($payload['is_subscribed'])) {
                $this->subscriptionManager->subscribe(
                    $payload['email'],
                    (int) $this->storeManager->getStore()->getId()
                );
            }

            $this->messageManager->addSuccessMessage(__(
                'Thanks — your message is with the wholesale desk. We aim to reply within one business day.'
            ));
            $this->dataPersistor->clear(FormData::PERSIST_KEY);

            return $redirect->setUrl($this->contactUsUrl([
                'sent' => '1',
                'topic' => $payload['enquiry_type'],
            ]));
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
            $this->dataPersistor->set(FormData::PERSIST_KEY, $this->request->getParams());
        } catch (\Exception $e) {
            $this->logger->critical($e);
            $this->messageManager->addErrorMessage(
                __('An error occurred while processing your form. Please try again later.')
            );
            $this->dataPersistor->set(FormData::PERSIST_KEY, $this->request->getParams());
        }

        return $redirect->setUrl($this->contactUsUrl());
    }

    /**
     * @param array<string, string> $query
     */
    private function contactUsUrl(array $query = []): string
    {
        return $this->urlBuilder->getUrl('', [
            '_direct' => 'contact-us',
            '_nosid' => true,
            '_query' => $query,
            '_fragment' => 'elme-contact-form',
        ]);
    }

    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return null;
    }

    /**
     * @return array<string, mixed>
     * @throws LocalizedException
     */
    private function validatedParams(): array
    {
        $name = trim((string) $this->request->getParam('name', ''));
        $email = trim((string) $this->request->getParam('email', ''));
        $telephone = trim((string) $this->request->getParam('telephone', ''));
        $company = trim((string) $this->request->getParam('company', ''));
        $businessType = trim((string) $this->request->getParam('business_type', ''));
        $enquiryType = trim((string) $this->request->getParam('enquiry_type', ''));
        $message = trim((string) $this->request->getParam('message', ''));
        $hideit = trim((string) $this->request->getParam('hideit', ''));

        if ($hideit !== '') {
            throw new \Exception('Bot submission blocked.');
        }
        if ($name === '') {
            throw new LocalizedException(__('Enter your name and try again.'));
        }
        if ($email === '' || !str_contains($email, '@')) {
            throw new LocalizedException(__('The email address is invalid. Verify the email address and try again.'));
        }
        if ($telephone === '') {
            throw new LocalizedException(__('Enter your phone number and try again.'));
        }
        if ($company === '') {
            throw new LocalizedException(__('Enter your company and try again.'));
        }
        if ($businessType === '' || !isset(FormData::BUSINESS_TYPES[$businessType])) {
            throw new LocalizedException(__('Select a business type and try again.'));
        }
        if ($enquiryType === '' || !isset(FormData::ENQUIRY_TYPES[$enquiryType])) {
            throw new LocalizedException(__('Select an enquiry type and try again.'));
        }
        if ($message === '') {
            throw new LocalizedException(__('Enter your message and try again.'));
        }
        if (mb_strlen($message) > FormData::MESSAGE_MAX) {
            throw new LocalizedException(
                __('Please enter no more than %1 characters.', FormData::MESSAGE_MAX)
            );
        }

        return [
            'name' => $name,
            'email' => $email,
            'telephone' => $telephone,
            'company' => $company,
            'business_type' => $businessType,
            'business_type_label' => $this->formData->getBusinessTypeLabel($businessType),
            'enquiry_type' => $enquiryType,
            'enquiry_type_label' => $this->formData->getEnquiryTypeLabel($enquiryType),
            'comment' => $message,
            'message' => $message,
            'is_subscribed' => (bool) $this->request->getParam('is_subscribed'),
        ];
    }
}
