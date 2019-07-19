<?php

/**
 * Class Columns
 * @author: Biplob Hossain <biplob@concrete5.co.jp>
 * @license MIT
 * Date: 2019-07-19
 */

namespace C5j\User;

use Concrete\Core\Attribute\Key\Key;
use UserAttributeKey;

class Columns
{
    protected $headers;
    protected $attributeKeys;

    /**
     * @return mixed
     */
    public function getHeaders()
    {
        if (!$this->headers) {
            $this->headers = $this->setDefaultHeaders();
        }

        return $this->headers;
    }

    /**
     * @param mixed $headers
     */
    public function setHeaders($headers)
    {
        $this->headers = $headers;
    }

    /**
     * Memoize the attribute keys so that we aren't looking them up over and over.
     *
     * @return array|Key[]
     */
    public function getAttributeKeys()
    {
        if (!isset($this->attributeKeys)) {
            $this->attributeKeys = UserAttributeKey::getList();
        }

        return $this->attributeKeys;
    }

    /**
     * @param array|Key[] $attributeKeys
     */
    public function setAttributeKeys($attributeKeys)
    {
        $this->attributeKeys = $attributeKeys;
    }

    private function setDefaultHeaders()
    {
        $headers = [
            'uID' => t('User ID'),
            'uName' => t('User Name'),
            'uEmail' => t('User Email'),
            'uTimeZone' => t('Time Zone'),
            'uDefaultLanguage' => t('Default Language'),
            'uDateAdded' => t('Date Added'),
            'uLastOnline' => t('Last Online'),
            'uLastLogin' => t('Last Login'),
            'uLastIP' => t('Last IP'),
            'uPreviousLogin' => t('Previous Login'),
            'uIsActive' => t('Is Active'),
            'uIsValidated' => t('Is Validated'),
            'uNumLogins' => t('Num of Logins'),
            'gName' => 'User Group',
        ];

        foreach ($headers as $handle => $name) {
            yield $handle => $name;
        }

        // Get headers for User attributes
        $attributes = $this->getAttributeKeys();
        foreach ($attributes as $attribute) {
            yield $attribute->getAttributeKeyHandle() => $attribute->getAttributeKeyDisplayName();
        }
    }
}