<?php

namespace Dashtainer\Domain;

use Dashtainer\Entity;
use Dashtainer\Form;
use Dashtainer\Repository;

class DockerProject
{
    /** @var Repository\DockerProjectRepository */
    protected $repo;

    public function __construct(Repository\DockerProjectRepository $repo)
    {
        $this->repo = $repo;
    }

    public function createProjectFromForm(
        Form\DockerProjectCreateUpdateForm $form,
        Entity\User $user
    ) : Entity\DockerProject {
        $project = new Entity\DockerProject();
        $project->fromArray($form->toArray());
        $project->setUser($user);

        $this->repo->save($project);

        $webNetwork = new Entity\DockerNetwork();
        $webNetwork->setName('web')
            ->setExternal('traefik_webgateway')
            ->setProject($project);

        $projectNetwork = new Entity\DockerNetwork();
        $projectNetwork->setName($project->getSlug())
            ->setProject($project);

        $project->addNetwork($webNetwork)
            ->addNetwork($projectNetwork);

        $this->repo->save($webNetwork, $projectNetwork, $project);

        return $project;
    }

    public function delete(Entity\DockerProject $project)
    {
        $deleted = [];
        $saved   = [];

        foreach ($project->getServices() as $service) {
            foreach ($service->getNetworks() as $network) {
                $service->removeNetwork($network);
                $network->removeService($service);

                $saved []= $network;
            }

            foreach ($service->getSecrets() as $secret) {
                $service->removeSecret($secret);
                $secret->removeService($service);

                $saved []= $secret;
            }

            foreach ($service->getVolumes() as $volume) {
                $service->removeVolume($volume);
                $volume->removeService($service);

                $saved []= $volume;
            }

            $service->setProject(null);
            $project->removeService($service);

            $saved []= $service;
            $deleted []= $service;
        }

        foreach ($project->getNetworks() as $network) {
            $project->removeNetwork($network);
            $deleted []= $network;
        }

        foreach ($project->getSecrets() as $secret) {
            $project->removeSecret($secret);
            $deleted []= $secret;
        }

        foreach ($project->getVolumes() as $volume) {
            $project->removeVolume($volume);
            $deleted []= $volume;
        }

        $deleted []= $project;

        $this->repo->save(...$saved);
        $this->repo->delete(...$deleted);
    }
}