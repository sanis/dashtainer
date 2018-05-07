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

    protected const SECRETS_REQUIRED = [
        'mysql_host',
        'mysql_root_password',
        'mysql_database',
        'mysql_user',
        'mysql_password',
    ];

    /**
     * @DashAssert\NonBlankString(message = "Version must be chosen")
     */
    public $version;

    public $port_confirm;

    public $port;

    public $port_used = false;

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

    /**
     * @Assert\Callback
     * @param ExecutionContextInterface $context
     * @param $payload
     */
    public function validate(ExecutionContextInterface $context, $payload)
    {
        parent::validate($context, $payload);

        $this->validatePort($context);
        $this->validateSystemFile($context);
        $this->validateUserFile($context);
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
