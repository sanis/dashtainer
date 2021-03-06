<?php

namespace Dashtainer\Domain\Docker;

use Dashtainer\Entity;
use Dashtainer\Form;
use Dashtainer\Repository;
use Dashtainer\Util;

class Service
{
    /** @var Repository\Docker\Service */
    protected $repo;

    /** @var ServiceManager */
    protected $manager;

    public function __construct(
        Repository\Docker\Service $repo,
        ServiceManager $manager
    ) {
        $this->repo    = $repo;
        $this->manager = $manager;
    }

    public function createService(
        Form\Docker\Service\CreateAbstract $form
    ) : Entity\Docker\Service {
        $handler = $this->manager->getWorkerFromForm($form);

        return $handler->create($form);
    }

    public function deleteService(Entity\Docker\Service $service)
    {
        $handler = $this->manager->getWorkerFromType($service->getType());

        $handler->delete($service);
    }

    public function updateService(
        Entity\Docker\Service $service,
        Form\Docker\Service\CreateAbstract $form
    ) : Entity\Docker\Service {
        $handler = $this->manager->getWorkerFromForm($form);

        return $handler->update($service, $form);
    }

    public function getCreateForm(
        Entity\Docker\ServiceType $serviceType
    ) : Form\Docker\Service\CreateAbstract {
        $handler = $this->manager->getWorkerFromType($serviceType);

        return $handler->getCreateForm($serviceType);
    }

    public function getCreateParams(
        Entity\Docker\Project $project,
        Entity\Docker\ServiceType $serviceType
    ) : array {
        $handler = $this->manager->getWorkerFromType($serviceType);

        return $handler->getCreateParams($project);
    }

    public function getViewParams(Entity\Docker\Service $service) : array
    {
        $handler = $this->manager->getWorkerFromType($service->getType());

        return $handler->getViewParams($service);
    }

    public function generateName(
        Entity\Docker\Project $project,
        Entity\Docker\ServiceType $serviceType,
        string $version = null
    ) : string {
        $services = $this->repo->findBy([
            'project' => $project,
            'type'    => $serviceType,
        ]);

        $version  = $version ? "-{$version}" : '';
        $hostname = Util\Strings::hostname("{$serviceType->getSlug()}{$version}");

        if (empty($services)) {
            return $hostname;
        }

        $usedNames = [];
        foreach ($services as $service) {
            $usedNames []= $service->getSlug();
        }

        for ($i = 1; $i < count($usedNames) + 2; $i++) {
            $name = "{$hostname}-{$i}";

            if (!in_array($name, $usedNames)) {
                return $name;
            }
        }

        return "{$hostname}-" . uniqid();
    }

    /**
     * Takes list of service names and returns names that
     * are not valid services in this project.
     *
     * @param Entity\Docker\Project $project
     * @param string[]              $servicesList
     * @return array
     */
    public function validateByName(
        Entity\Docker\Project $project,
        array $servicesList
    ) : array {
        $services = $this->repo->findBy([
            'project' => $project,
            'name'    => $servicesList,
        ]);

        if (count($services) === count($servicesList)) {
            return [];
        }

        $servicesFound = [];
        foreach ($services as $service) {
            $servicesFound []= $service->getName();
        }

        return array_diff($servicesList, $servicesFound);
    }
}
