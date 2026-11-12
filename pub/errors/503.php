<?php
/**
 * Copyright 2011 Adobe
 * All Rights Reserved.
 *
 * Elme (FE-94): add Retry-After so maintenance / 503 responses tell crawlers when to retry.
 */

require_once 'processorFactory.php';

$processorFactory = new \Magento\Framework\Error\ProcessorFactory();
$processor = $processorFactory->createProcessor();
$response = $processor->process503();
$response->setHeader('Retry-After', '3600', true);
$response->sendResponse();
