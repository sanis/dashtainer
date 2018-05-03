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

    public $secret = [
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

        $this->validatePort($context);
        $this->validateSecret($context);
        $this->validateSystemFile($context);
        $this->validateUserFile($context);
    }

    protected function validateSecret(ExecutionContextInterface $context)
    {
        $validator = $context->getValidator();

        $assertSecretName     = new DashAssert\SecretName();
        $assertNonBlankString = new DashAssert\NonBlankString();

        $nameError  = 'You must enter a name for this secret, Valid characters are a-zA-Z0-9 _ and -';
        $valueError = 'You must enter a value';

        foreach (static::SECRETS_REQUIRED as $secretName) {
            if (empty($this->secret[$secretName])) {
                $context->buildViolation('These fields must be filled in.')
                    ->atPath("secret-{$secretName}-name,secret-{$secretName}-value")
                    ->addViolation();

                continue;
            }
        }

        foreach ($this->secret as $secretName => $data) {
            $error = $validator->validate(
                $data['name'],
                $assertSecretName
            );

            if (count($error) > 0) {
                $context->buildViolation($nameError)
                    ->atPath("secret-{$secretName}-name")
                    ->addViolation();
            }

            $error = $validator->validate(
                $data['name'],
                $assertNonBlankString
            );

            if (count($error) > 0) {
                $context->buildViolation($nameError)
                    ->atPath("secret-{$secretName}-name")
                    ->addViolation();
            }

            $error = $validator->validate(
                $data['value'],
                $assertNonBlankString
            );

            if (count($error) > 0) {
                $context->buildViolation($valueError)
                    ->atPath("secret-{$secretName}-value")
                    ->addViolation();
            }
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
