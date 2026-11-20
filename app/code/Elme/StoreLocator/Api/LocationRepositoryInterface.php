<?php

declare(strict_types=1);

namespace Elme\StoreLocator\Api;

use Elme\StoreLocator\Api\Data\LocationInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;

interface LocationRepositoryInterface
{
    /**
     * @throws CouldNotSaveException
     */
    public function save(LocationInterface $location): LocationInterface;

    /**
     * @throws NoSuchEntityException
     */
    public function getById(int $locationId): LocationInterface;

    /**
     * @throws NoSuchEntityException
     */
    public function getByUrlKey(string $urlKey): LocationInterface;

    /**
     * @throws CouldNotDeleteException
     */
    public function delete(LocationInterface $location): bool;

    /**
     * @throws CouldNotDeleteException
     * @throws NoSuchEntityException
     */
    public function deleteById(int $locationId): bool;
}
