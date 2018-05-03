<?php

namespace Dashtainer\Domain\Docker\ServiceWorker;

use Dashtainer\Entity;
use Dashtainer\Form;

class MariaDB extends WorkerAbstract implements WorkerInterface
{
    public function getServiceTypeSlug() : string
    {
        return 'mariadb';
    }

    public function getCreateForm() : Form\Docker\Service\CreateAbstract
    {
        return new Form\Docker\Service\MariaDBCreate();
    }

    /**
     * @param Form\Docker\Service\MariaDBCreate $form
     * @return Entity\Docker\Service
     */
    public function create($form) : Entity\Docker\Service
    {
        $service = new Entity\Docker\Service();
        $service->setName($form->name)
            ->setType($form->type)
            ->setProject($form->project);

        $version = (string) number_format($form->version, 1);

        $service->setImage("mariadb:{$version}")
            ->setRestart(Entity\Docker\Service::RESTART_ALWAYS);

        $sec = '/run/secrets/';
        $service->setEnvironments([
            'MYSQL_ROOT_PASSWORD_FILE' => $sec . $form->secret['mysql_root_password']['name'],
            'MYSQL_DATABASE_FILE'      => $sec . $form->secret['mysql_database']['name'],
            'MYSQL_USER_FILE'          => $sec . $form->secret['mysql_user']['name'],
            'MYSQL_PASSWORD_FILE'      => $sec . $form->secret['mysql_password']['name'],
        ]);

        $this->serviceRepo->save($service);

        $slug = $service->getSlug();

        $secretDbRootPw = new Entity\Docker\Secret();
        $secretDbRootPw->setName("{$slug}-mysql_root_password")
            ->setFile($form->secret['mysql_root_password']['name'])
            ->setContents($form->secret['mysql_root_password']['value'])
            ->setProject($form->project)
            ->addService($service);

        $secretDbName = new Entity\Docker\Secret();
        $secretDbName->setName("{$slug}-mysql_database")
            ->setFile($form->secret['mysql_database']['name'])
            ->setContents($form->secret['mysql_database']['value'])
            ->setProject($form->project)
            ->addService($service);

        $secretDbUser = new Entity\Docker\Secret();
        $secretDbUser->setName("{$slug}-mysql_user")
            ->setFile($form->secret['mysql_user']['name'])
            ->setContents($form->secret['mysql_user']['value'])
            ->setProject($form->project)
            ->addService($service);

        $secretDbPassword = new Entity\Docker\Secret();
        $secretDbPassword->setName("{$slug}-mysql_password")
            ->setFile($form->secret['mysql_password']['name'])
            ->setContents($form->secret['mysql_password']['value'])
            ->setProject($form->project)
            ->addService($service);

        $service->addSecret($secretDbRootPw)
            ->addSecret($secretDbName)
            ->addSecret($secretDbUser)
            ->addSecret($secretDbPassword);

        $this->serviceRepo->save(
            $secretDbRootPw,
            $secretDbName,
            $secretDbUser,
            $secretDbPassword,
            $service
        );

        $this->addToPrivateNetworks($service, $form);

        $versionMeta = new Entity\Docker\ServiceMeta();
        $versionMeta->setName('version')
            ->setData([$form->version])
            ->setService($service);

        $service->addMeta($versionMeta);

        $portMetaData = $form->port_confirm ? [$form->port] : [];
        $servicePort  = $form->port_confirm ? ["{$form->port}:3306"] : [];

        $portMeta = new Entity\Docker\ServiceMeta();
        $portMeta->setName('bind-port')
            ->setData($portMetaData)
            ->setService($service);

        $service->addMeta($portMeta)
            ->setPorts($servicePort);

        $this->serviceRepo->save($versionMeta, $portMeta, $service);

        $myCnf = new Entity\Docker\ServiceVolume();
        $myCnf->setName('my.cnf')
            ->setSource("\$PWD/{$service->getSlug()}/my.cnf")
            ->setTarget('/etc/mysql/my.cnf')
            ->setData($form->system_file['my.cnf'] ?? '')
            ->setConsistency(Entity\Docker\ServiceVolume::CONSISTENCY_DELEGATED)
            ->setOwner(Entity\Docker\ServiceVolume::OWNER_SYSTEM)
            ->setFiletype(Entity\Docker\ServiceVolume::FILETYPE_FILE)
            ->setService($service);

        $configFileCnf = new Entity\Docker\ServiceVolume();
        $configFileCnf->setName('config-file.cnf')
            ->setSource("\$PWD/{$service->getSlug()}/config-file.cnf")
            ->setTarget('/etc/mysql/conf.d/config-file.cnf')
            ->setData($form->system_file['config-file.cnf'] ?? '')
            ->setConsistency(Entity\Docker\ServiceVolume::CONSISTENCY_DELEGATED)
            ->setOwner(Entity\Docker\ServiceVolume::OWNER_SYSTEM)
            ->setFiletype(Entity\Docker\ServiceVolume::FILETYPE_FILE)
            ->setService($service);

        $service->addVolume($myCnf)
            ->addVolume($configFileCnf);

        $this->serviceRepo->save($myCnf, $configFileCnf, $service);

        $this->createDatastore($service, $form, '/var/lib/mysql');

        $this->userFilesCreate($service, $form);

        return $service;
    }

    public function getCreateParams(Entity\Docker\Project $project) : array
    {
        $form = $this->getCreateForm();
        $form->project = $project;

        $this->generateSecretsNames($form);

        return [
            'bindPort' => $this->getOpenBindPort($project),
            'secret'   => $form->secret,
        ];
    }

    public function getViewParams(Entity\Docker\Service $service) : array
    {
        $version   = $service->getMeta('version')->getData()[0];
        $version   = (string) number_format($version, 1);
        $datastore = $service->getMeta('datastore')->getData()[0];

        $bindPortMeta = $service->getMeta('bind-port');
        $bindPort     = $bindPortMeta->getData()[0]
            ?? $this->getOpenBindPort($service->getProject());
        $portConfirm  = $bindPortMeta->getData()[0] ?? false;

        $env = $service->getEnvironments();

        $mysql_root_password = $env['MYSQL_ROOT_PASSWORD'];
        $mysql_database      = $env['MYSQL_DATABASE'];
        $mysql_user          = $env['MYSQL_USER'];
        $mysql_password      = $env['MYSQL_PASSWORD'];

        $myCnf         = $service->getVolume('my.cnf');
        $configFileCnf = $service->getVolume('config-file.cnf');

        $userFiles = $service->getVolumesByOwner(
            Entity\Docker\ServiceVolume::OWNER_USER
        );

        return [
            'version'             => $version,
            'datastore'           => $datastore,
            'bindPort'            => $bindPort,
            'portConfirm'         => $portConfirm,
            'mysql_root_password' => $mysql_root_password,
            'mysql_database'      => $mysql_database,
            'mysql_user'          => $mysql_user,
            'mysql_password'      => $mysql_password,
            'systemFiles'         => [
                'my.cnf'          => $myCnf,
                'config-file.cnf' => $configFileCnf,
            ],
            'userFiles'           => $userFiles,
        ];
    }

    /**
     * @param Entity\Docker\Service             $service
     * @param Form\Docker\Service\MariaDBCreate $form
     * @return Entity\Docker\Service
     */
    public function update(
        Entity\Docker\Service $service,
        $form
    ) : Entity\Docker\Service {
        $service->setEnvironments([
            'MYSQL_ROOT_PASSWORD' => $form->mysql_root_password,
            'MYSQL_DATABASE'      => $form->mysql_database,
            'MYSQL_USER'          => $form->mysql_user,
            'MYSQL_PASSWORD'      => $form->mysql_password,
        ]);

        $this->addToPrivateNetworks($service, $form);

        $portMetaData = $form->port_confirm ? [$form->port] : [];
        $servicePort  = $form->port_confirm ? ["{$form->port}:3306"] : [];

        $portMeta = $service->getMeta('bind-port');
        $portMeta->setData($portMetaData);

        $this->serviceRepo->save($portMeta);

        $service->setPorts($servicePort);

        $myCnf = $service->getVolume('my.cnf');
        $myCnf->setData($form->system_file['my.cnf'] ?? '');

        $configFileCnf = $service->getVolume('config-file.cnf');
        $configFileCnf->setData($form->system_file['config-file.cnf'] ?? '');

        $this->serviceRepo->save($myCnf, $configFileCnf);

        $this->updateDatastore($service, $form);

        $this->userFilesUpdate($service, $form);

        return $service;
    }

    protected function getOpenBindPort(Entity\Docker\Project $project) : int
    {
        $bindPortMetas = $this->serviceRepo->getProjectBindPorts($project);

        $ports = [];
        foreach ($bindPortMetas as $meta) {
            if (!$data = $meta->getData()) {
                continue;
            }

            $ports []= $data[0];
        }

        for ($i = 3307; $i < 65535; $i++) {
            if (!in_array($i, $ports)) {
                return $i;
            }
        }

        return 3306;
    }
}
