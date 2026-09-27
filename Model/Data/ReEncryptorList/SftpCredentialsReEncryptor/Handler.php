<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Nx6\PedidosYa\Model\Data\ReEncryptorList\SftpCredentialsReEncryptor;

use Magento\EncryptionKey\Model\Data\ReEncryptorList\ReEncryptor\Handler\ErrorFactory;
use Magento\EncryptionKey\Model\Data\ReEncryptorList\ReEncryptor\HandlerInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Query\Generator;
use Magento\Framework\Encryption\EncryptorInterface;

/**
 * Handler for re-encryption of SFTP passwords on PedidosYa export profiles.
 *
 * Mirrors Magento\Config\Model\Data\ReEncryptorList\CoreConfigDataReEncryptor\Handler -- these
 * profile tables aren't covered by Magento's own built-in re-encryptors, so `encryption:key:change`
 * / `encryption:data:re-encrypt` silently skip them unless registered here (see the CVE-2026-75650
 * crypt-key rotation, 2026-09-14).
 */
class Handler implements HandlerInterface
{
    /**
     * @var string
     */
    private const string PATTERN = "^[[:digit:]]+:[[:digit:]]+:.*$";

    /**
     * @var array<string,string> table name => primary key column
     */
    private const array TABLES = [
        'nx6_pedidosya_products_profile' => 'products_profile_id',
        'nx6_pedidosya_promo_profile' => 'promo_profile_id',
    ];

    /**
     * @var string
     */
    private const int BATCH_SIZE = 1000;

    public function __construct(
        private readonly EncryptorInterface $encryptor,
        private readonly ResourceConnection $resourceConnection,
        private readonly ErrorFactory $errorFactory,
        private readonly Generator $queryGenerator
    ) {
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public function reEncrypt(): array
    {
        $errors = [];
        $adapter = $this->resourceConnection->getConnection();

        foreach (self::TABLES as $table => $identifier) {
            $tableName = $this->resourceConnection->getTableName($table);

            $select = $adapter->select()
                ->from($tableName, [$identifier, 'sftp_password'])
                ->where('sftp_password != ?', '')
                ->where('sftp_password IS NOT NULL')
                ->where('sftp_password REGEXP ?', self::PATTERN);

            $iterator = $this->queryGenerator->generate(
                $identifier,
                $select,
                self::BATCH_SIZE
            );

            foreach ($iterator as $batch) {
                foreach ($adapter->fetchAll($batch) as $row) {
                    try {
                        $adapter->update(
                            $tableName,
                            [
                                'sftp_password' => $this->encryptor->encrypt(
                                    $this->encryptor->decrypt($row['sftp_password'])
                                ),
                            ],
                            [$identifier . ' = ?' => $row[$identifier]]
                        );
                    } catch (\Throwable $e) {
                        $errors[] = $this->errorFactory->create(
                            $identifier,
                            $row[$identifier],
                            $e->getMessage()
                        );

                        continue;
                    }
                }
            }
        }

        return $errors;
    }
}
