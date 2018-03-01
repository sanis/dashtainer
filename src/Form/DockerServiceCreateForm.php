<?php

namespace Dashtainer\Form;

use Dashtainer\Util;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class DockerServiceCreateForm
{
    use Util\HydratorTrait;

    /**
     * @Assert\NotBlank(message = "Please enter a service name.")
     */
    public $name;

    /**
     * @Assert\NotBlank(message = "Please choose a service type.")
     */
    public $service_type;

    /**
     * @Assert\NotBlank(message = "Invalid project selected.")
     */
    public $project;

    /**
     * @Assert\Callback
     * @param ExecutionContextInterface $context
     * @param $payload
     */
    public function validate(ExecutionContextInterface $context, $payload)
    {
    }
}