<?php

namespace Dashtainer\Form\Docker\Service;

use Dashtainer\Util;
use Dashtainer\Validator\Constraints as DashAssert;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class MariaDBCreate extends CreateAbstract implements Util\HydratorInterface
{
    use Util\HydratorTrait;
    use DashAssert\DatastoreTrait;
    use DashAssert\SystemFileTrait;
    use DashAssert\UserFileTrait;

    /**
     * @DashAssert\NonBlankString(message = "Version must be chosen")
     */
    public $version;

    public $port_confirm;

    public $port;

    public $port_used = false;

    public $secret_store;

    /**
     * @DashAssert\Hostname
     */
    public $mysql_root_password;

    /**
     * @DashAssert\Hostname
     */
    public $mysql_database;

    /**
     * @DashAssert\Hostname
     */
    public $mysql_user;

    /**
     * @DashAssert\Hostname
     */
    public $mysql_password;

    public $secret_docker = [
        'mysql_root_password' => [
            'name'  => 'mysql_root_password',
            'value' => '',
        ],
        'mysql_database'      => [
            'name'  => 'mysql_database',
            'value' => '',
        ],
        'mysql_user'          => [
            'name'  => 'mysql_user',
            'value' => '',
        ],
        'mysql_password'      => [
            'name'  => 'mysql_password',
            'value' => '',
        ],
    ];

    /**
     * @Assert\Callback
     * @param ExecutionContextInterface $context
     * @param $payload
     */
    public function validate(ExecutionContextInterface $context, $payload)
    {
        parent::validate($context, $payload);

        $this->validationSecret($context);
        $this->validatePort($context);
        $this->validateSystemFile($context);
        $this->validateUserFile($context);
    }

    protected function validationSecret(ExecutionContextInterface $context)
    {
        if ($this->credential_store !== 'secret') {
            return;
        }

        foreach ($this->secret as $key => $data) {
            if (empty($data['name'])) {
                $context->buildViolation('You must enter a name for this secret file')
                    ->atPath("secret-{$data['name']}")
                    ->addViolation();
            }

            if (empty($data['value'])) {
                $context->buildViolation('You must enter a value for this secret')
                    ->atPath("secret-{$data['name']}")
                    ->addViolation();
            }
        }
    }

    protected function validateCredentials(ExecutionContextInterface $context)
    {
        if ($this->credential_store !== 'plaintext') {
            return;
        }
    }

    protected function validatePort(ExecutionContextInterface $context)
    {
        if (!$this->port_confirm) {
            return;
        }

        if (empty($this->port) || !is_numeric($this->port)) {
            $context->buildViolation('You must enter a port')
                ->atPath('port')
                ->addViolation();

            return;
        }

        if ($this->port < 3306 || $this->port > 65535) {
            $context->buildViolation('Port must be between 3306 and 65535')
                ->atPath('port')
                ->addViolation();
        }

        if ($this->port_used) {
            $context->buildViolation('Port already used in a different service')
                ->atPath('port')
                ->addViolation();
        }
    }
}
