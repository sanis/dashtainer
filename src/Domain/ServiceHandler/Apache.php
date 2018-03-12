<?php

namespace Dashtainer\Domain\ServiceHandler;

use Dashtainer\Entity;
use Dashtainer\Form;
use Dashtainer\Repository;

class Apache extends HandlerAbstract implements CrudInterface
{
    /** @var Repository\DockerNetworkRepository */
    protected $networkRepo;

    /** @var Repository\DockerServiceTypeRepository */
    protected $serviceTypeRepo;

    public function __construct(
        Repository\DockerProjectRepository $projectRepo,
        Repository\DockerServiceRepository $serviceRepo,
        Repository\DockerServiceTypeRepository $serviceTypeRepo,
        Repository\DockerNetworkRepository $networkRepo
    ) {
        $this->networkRepo     = $networkRepo;
        $this->serviceRepo     = $serviceRepo;
        $this->serviceTypeRepo = $serviceTypeRepo;
    }

    public function getServiceTypeSlug() : string
    {
        return 'apache';
    }

    public function getCreateForm(
        Entity\DockerServiceType $serviceType = null
    ) : Form\Service\CreateAbstract {
        return new Form\Service\ApacheCreate();
    }

    /**
     * @param Form\Service\ApacheCreate $form
     * @return Entity\DockerService
     */
    public function create($form) : Entity\DockerService
    {
        $service = new Entity\DockerService();
        $service->setName($form->name)
            ->setType($form->type)
            ->setProject($form->project);

        $build = $service->getBuild();
        $build->setContext("./{$service->getSlug()}")
            ->setDockerfile('Dockerfile')
            ->setArgs([
                'SYSTEM_PACKAGES'       => array_unique($form->system_packages),
                'APACHE_MODULES_ENABLE' => array_unique($form->enabled_modules),
                'APACHE_MODULES_DISABLE'=> array_unique($form->disabled_modules),
            ]);

        $service->setBuild($build);

        $privateNetwork = $this->networkRepo->getPrimaryPrivateNetwork(
            $service->getProject()
        );

        $publicNetwork = $this->networkRepo->getPrimaryPublicNetwork(
            $service->getProject()
        );

        $service->addNetwork($privateNetwork)
            ->addNetwork($publicNetwork);

        $this->serviceRepo->save($service, $privateNetwork, $publicNetwork);

        $vhost = [
            'server_name'   => $form->server_name,
            'server_alias'  => $form->server_alias,
            'document_root' => $form->document_root,
            'fcgi_handler'  => $form->fcgi_handler,
        ];

        $vhostMeta = new Entity\DockerServiceMeta();
        $vhostMeta->setName('vhost')
            ->setData($vhost)
            ->setService($service);

        $service->addMeta($vhostMeta);

        $this->serviceRepo->save($vhostMeta, $service);

        $apache2Conf = new Entity\DockerServiceVolume();
        $apache2Conf->setName('apache2.conf')
            ->setSource("\$PWD/{$service->getSlug()}/apache2.conf")
            ->setTarget('/etc/apache2/apache2.conf')
            ->setData($form->file['apache2.conf'] ?? '')
            ->setConsistency(Entity\DockerServiceVolume::CONSISTENCY_DELEGATED)
            ->setOwner(Entity\DockerServiceVolume::OWNER_SYSTEM)
            ->setFiletype(Entity\DockerServiceVolume::FILETYPE_FILE)
            ->setService($service);

        $portsConf = new Entity\DockerServiceVolume();
        $portsConf->setName('ports.conf')
            ->setSource("\$PWD/{$service->getSlug()}/ports.conf")
            ->setTarget('/etc/apache2/ports.conf')
            ->setData($form->file['ports.conf'] ?? '')
            ->setConsistency(Entity\DockerServiceVolume::CONSISTENCY_DELEGATED)
            ->setOwner(Entity\DockerServiceVolume::OWNER_SYSTEM)
            ->setFiletype(Entity\DockerServiceVolume::FILETYPE_FILE)
            ->setService($service);

        $vhostConf = new Entity\DockerServiceVolume();
        $vhostConf->setName('vhost.conf')
            ->setSource("\$PWD/{$service->getSlug()}/vhost.conf")
            ->setTarget('/etc/apache2/sites-enabled/000-default.conf')
            ->setData($form->vhost_conf ?? '')
            ->setConsistency(Entity\DockerServiceVolume::CONSISTENCY_DELEGATED)
            ->setOwner(Entity\DockerServiceVolume::OWNER_SYSTEM)
            ->setFiletype(Entity\DockerServiceVolume::FILETYPE_FILE)
            ->setService($service);

        $service->addVolume($apache2Conf)
            ->addVolume($portsConf)
            ->addVolume($vhostConf);

        $this->serviceRepo->save($apache2Conf, $portsConf, $vhostConf, $service);

        $this->projectFilesCreate($service, $form);

        $this->customFilesCreate($service, $form);

        return $service;
    }

    public function getCreateParams(Entity\DockerProject $project) : array
    {
        $phpFpmType = $this->serviceTypeRepo->findBySlug('php-fpm');

        $phpFpmServices = $this->serviceRepo->findByProjectAndType(
            $project,
            $phpFpmType
        );

        return [
            'fcgi_handlers' => [
                'phpfpm' => $phpFpmServices,
            ],
        ];
    }

    public function getViewParams(Entity\DockerService $service) : array
    {
        $apacheModulesEnable    = $service->getBuild()->getArgs()['APACHE_MODULES_ENABLE'];
        $apacheModulesDisable   = $service->getBuild()->getArgs()['APACHE_MODULES_DISABLE'];
        $systemPackagesSelected = $service->getBuild()->getArgs()['SYSTEM_PACKAGES'];

        $availableApacheModules = [];

        $apacheModules          = $service->getType()->getMeta('modules');
        $availableApacheModules += $apacheModules->getData()['default'];
        $availableApacheModules += $apacheModules->getData()['available'];

        $apache2Conf = $service->getVolume('apache2.conf');
        $portsConf   = $service->getVolume('ports.conf');
        $vhostConf   = $service->getVolume('vhost.conf');
        $customFiles = $service->getVolumesByOwner(Entity\DockerServiceVolume::OWNER_USER);

        $vhostMeta = $service->getMeta('vhost');

        $phpFpmType = $this->serviceTypeRepo->findBySlug('php-fpm');

        $phpFpmServices = $this->serviceRepo->findByProjectAndType(
            $service->getProject(),
            $phpFpmType
        );

        return [
            'projectFiles'           => $this->projectFilesViewParams($service),
            'apacheModulesEnable'    => $apacheModulesEnable,
            'apacheModulesDisable'   => $apacheModulesDisable,
            'availableApacheModules' => $availableApacheModules,
            'systemPackagesSelected' => $systemPackagesSelected,
            'configFiles'            => [
                'apache2.conf' => $apache2Conf,
                'ports.conf'   => $portsConf,
            ],
            'customFiles'            => $customFiles,
            'vhost'                  => [
                'server_name'   => $vhostMeta->getData()['server_name'],
                'server_alias'  => $vhostMeta->getData()['server_alias'],
                'document_root' => $vhostMeta->getData()['document_root'],
                'fcgi_handler'  => $vhostMeta->getData()['fcgi_handler'],
            ],
            'vhost_conf'             => $vhostConf,
            'fcgi_handlers'          => [
                'phpfpm' => $phpFpmServices,
            ],
        ];
    }

    /**
     * @param Entity\DockerService      $service
     * @param Form\Service\ApacheCreate $form
     * @return Entity\DockerService
     */
    public function update(
        Entity\DockerService $service,
        $form
    ) : Entity\DockerService {
        $build = $service->getBuild();
        $build->setContext("./{$service->getSlug()}")
            ->setDockerfile('DockerFile')
            ->setArgs([
                'SYSTEM_PACKAGES'       => array_unique($form->system_packages),
                'APACHE_MODULES_ENABLE' => array_unique($form->enabled_modules),
                'APACHE_MODULES_DISABLE'=> array_unique($form->disabled_modules),
            ]);

        $service->setBuild($build);

        $this->serviceRepo->save($service);

        $vhost = [
            'server_name'   => $form->server_name,
            'server_alias'  => $form->server_alias,
            'document_root' => $form->document_root,
            'fcgi_handler'  => $form->fcgi_handler,
        ];

        $vhostMeta = $service->getMeta('vhost');
        $vhostMeta->setData($vhost);

        $this->serviceRepo->save($vhostMeta);

        $apache2Conf = $service->getVolume('apache2.conf');
        $apache2Conf->setData($form->file['apache2.conf'] ?? '');

        $portsConf   = $service->getVolume('ports.conf');
        $portsConf->setData($form->file['ports.conf'] ?? '');

        $vhostConf   = $service->getVolume('vhost.conf');
        $vhostConf->setData($form->vhost_conf);

        $this->serviceRepo->save($apache2Conf, $portsConf, $vhostConf);

        $this->projectFilesUpdate($service, $form);

        $this->customFilesUpdate($service, $form);

        return $service;
    }
}