<?php
/**
 * Magelan_SearchCms
 */
declare(strict_types=1);

namespace Magelan\SearchCms\Model\Config\Backend;

use Magelan\SearchCms\Model\Config;
use Magento\Framework\App\Config\Value;
use Magento\Framework\Exception\ValidatorException;

/**
 * "Excluded pages": one CMS page identifier per line, a trailing "*" allowed. Saved normalised
 * (trimmed, one per line, no duplicates); an entry with a space or other characters a URL key cannot
 * contain is refused with a message naming it.
 */
class Exclusions extends Value
{
    /**
     * @return $this
     * @throws ValidatorException
     */
    public function beforeSave()
    {
        $list = Config::parseIdentifierList((string) $this->getValue());
        $invalid = [];
        foreach ($list as $identifier) {
            if (preg_match('#^[A-Za-z0-9_\-./]+\*?$#', $identifier) !== 1) {
                $invalid[] = $identifier;
            }
        }
        if ($invalid) {
            throw new ValidatorException(__(
                'Excluded pages: "%1" is not a page identifier. Enter one identifier per line (letters, digits, "-", "_", "/"; "*" only at the end).',
                implode('", "', $invalid)
            ));
        }
        $this->setValue(implode("\n", $list));

        return parent::beforeSave();
    }
}
