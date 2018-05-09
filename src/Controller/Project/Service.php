<?php

namespace Dashtainer\Controller\Project;

use Dashtainer\Domain;
use Dashtainer\Entity;
use Dashtainer\Form;
use Dashtainer\Repository;
use Dashtainer\Response\AjaxResponse;
use Dashtainer\Validator;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class Service extends Controller
{
    /** @var Domain\Docker\Network */
    protected $dNetworkDomain;

    /** @var Domain\Docker\Service */
    protected $dServiceDomain;

    /** @var Repository\Docker\Network */
    protected $dNetworkRepo;

    /** @var Repository\Docker\Project */
    protected $dProjectRepo;

    /** @var Repository\Docker\Secret */
    protected $dSecretRepo;

    /** @var Repository\Docker\Service */
    protected $dServiceRepo;

    /** @var Repository\Docker\ServiceCategory */
    protected $dServiceCatRepo;

    /** @var Repository\Docker\ServiceType */
    protected $dServiceTypeRepo;

    /** @var Validator\Validator */
    protected $validator;

    public function __construct(
        Domain\Docker\Network $dNetworkDomain,
        Domain\Docker\Service $dServiceDomain,
        Repository\Docker\Network $dNetworkRepo,
        Repository\Docker\Project $dProjectRepo,
        Repository\Docker\Secret $dSecretRepo,
        Repository\Docker\Service $dServiceRepo,
        Repository\Docker\ServiceCategory $dServiceCatRepo,
        Repository\Docker\ServiceType $dServiceTypeRepo,
        Validator\Validator $validator
    ) {
        $this->dNetworkDomain = $dNetworkDomain;
        $this->dServiceDomain = $dServiceDomain;

        $this->dNetworkRepo     = $dNetworkRepo;
        $this->dProjectRepo     = $dProjectRepo;
        $this->dSecretRepo      = $dSecretRepo;
        $this->dServiceRepo     = $dServiceRepo;
        $this->dServiceCatRepo  = $dServiceCatRepo;
        $this->dServiceTypeRepo = $dServiceTypeRepo;

        $this->validator = $validator;
    }

    /**
     * @Route(name="project.service.index.get",
     *     path="/project/{projectId}/service",
     *     methods={"GET"}
     * )
     * @param Entity\User $user
     * @param string      $projectId
     * @return Response
     */
    public function getIndex(
        Entity\User $user,
        string $projectId
    ) : Response {
        if (!$project = $this->dProjectRepo->findByUser($user, $projectId)) {
            return $this->render('@Dashtainer/project/not-found.html.twig');
        }

        return $this->render('@Dashtainer/project/service/index.html.twig', [
            'user'              => $user,
            'project'           => $project,
            'serviceCategories' => $this->dServiceCatRepo->findAll(),
        ]);
    }

    /**
     * @Route(name="project.service.block-add-file.get",
     *     path="/project/{projectId}/service/block-add-file/{language}/{targetPath}",
     *     methods={"GET"}
     * )
     * @param string $language
     * @param string $targetPath
     * @return AjaxResponse
     */
    public function getBlockAddFile(
        string $language,
        string $targetPath = null
    ) : AjaxResponse {
        $uniqid = uniqid();

        $id   = "user_file-{$uniqid}";
        $name = "user_file[{$uniqid}]";

        $tabTemplate = '@Dashtainer/project/service/snippets/service_user_file_tab.html.twig';
        $blockTab    = $this->render($tabTemplate, [
            'id'             => $id,
            'name'           => $name,
            'errorContainer' => 'user_file-error',
        ]);

        $blockTemplate = '@Dashtainer/project/service/snippets/service_user_file_content.html.twig';
        $blockContent  = $this->render($blockTemplate, [
            'id'             => $id,
            'name'           => $name,
            'language'       => $language,
            'targetPath'     => $targetPath ? urldecode($targetPath) : '',
            'errorContainer' => 'user_file-error',
        ]);

        return new AjaxResponse([
            'type' => AjaxResponse::AJAX_SUCCESS,
            'data' => [
                'tab'     => $blockTab->getContent(),
                'content' => $blockContent->getContent(),
            ],
        ], AjaxResponse::HTTP_OK);
    }

    /**
     * @Route(name="project.service.block-add-secret.get",
     *     path="/project/{projectId}/service/block-add-secret",
     *     methods={"GET"}
     * )
     * @return AjaxResponse
     */
    public function getBlockAddSecret() : AjaxResponse
    {
        $template = '@Dashtainer/project/service/snippets/secret-add.html.twig';
        $rendered = $this->render($template, [
            'id' => uniqid(),
        ]);

        return new AjaxResponse([
            'type' => AjaxResponse::AJAX_SUCCESS,
            'data' => $rendered->getContent(),
        ], AjaxResponse::HTTP_OK);
    }

    /**
     * @Route(name="project.service.network-block-create.get",
     *     path="/project/{projectId}/service/network-block-create",
     *     methods={"GET"}
     * )
     * @param Entity\User $user
     * @param string      $projectId
     * @return            AjaxResponse
     */
    public function getNetworkBlockCreate(
        Entity\User $user,
        string $projectId
    ) : AjaxResponse {
        if (!$project = $this->dProjectRepo->findByUser($user, $projectId)) {
            return new AjaxResponse([
                'type' => AjaxResponse::AJAX_REDIRECT,
                'data' => '',
            ], AjaxResponse::HTTP_BAD_REQUEST);
        }

        $uniqid = uniqid();

        $id          = "network_new-{$uniqid}";
        $name        = "network_new[{$uniqid}]";
        $networkName = $this->dNetworkDomain->generateName($project);

        $blockTemplate = '@Dashtainer/project/service/snippets/network_new_card.html.twig';
        $blockContent  = $this->render($blockTemplate, [
            'id'             => $id,
            'name'           => $name,
            'errorContainer' => 'network_new',
            'networkName'    => $networkName,
            'removable'      => true,
        ]);

        return new AjaxResponse([
            'type' => AjaxResponse::AJAX_SUCCESS,
            'data' => [
                'content' => $blockContent->getContent(),
            ],
        ], AjaxResponse::HTTP_OK);
    }

    /**
     * @Route(name="project.service.create.get",
     *     path="/project/{projectId}/service/create/{serviceTypeSlug}/{version}",
     *     methods={"GET"}
     * )
     * @param Entity\User $user
     * @param string      $projectId
     * @param string      $serviceTypeSlug
     * @param string|null $version
     * @return Response
     */
    public function getCreate(
        Entity\User $user,
        string $projectId,
        string $serviceTypeSlug,
        string $version = null
    ) : Response {
        if (!$project = $this->dProjectRepo->findByUser($user, $projectId)) {
            return $this->render('@Dashtainer/project/not-found.html.twig');
        }

        if (!$serviceType = $this->dServiceTypeRepo->findBySlug($serviceTypeSlug)) {
            return $this->render('@Dashtainer/project/not-found.html.twig');
        }

        $networks = $this->dNetworkRepo->getPrivateNetworks($project);

        $serviceName = $this->dServiceDomain->generateName(
            $project,
            $serviceType,
            $version
        );

        $networkName = $this->dNetworkDomain->generateName($project);

        $template = sprintf('@Dashtainer/project/service/%s/create.html.twig',
            strtolower($serviceTypeSlug)
        );

        $params = $this->dServiceDomain->getCreateParams($project, $serviceType);

        return $this->render($template, array_merge([
            'user'              => $user,
            'project'           => $project,
            'serviceCategories' => $this->dServiceCatRepo->findAll(),
            'serviceName'       => $serviceName,
            'serviceType'       => $serviceType,
            'version'           => $version,
            'networks'          => $networks,
            'networkName'       => $networkName,
        ], $params));
    }

    /**
     * @Route(name="project.service.create.post",
     *     path="/project/{projectId}/service/create/{serviceTypeSlug}",
     *     methods={"POST"}
     * )
     * @param Request     $request
     * @param Entity\User $user
     * @param string      $projectId
     * @param string      $serviceTypeSlug
     * @return AjaxResponse
     */
    public function postCreate(
        Request $request,
        Entity\User $user,
        string $projectId,
        string $serviceTypeSlug
    ) : AjaxResponse {
        $project = $this->dProjectRepo->findByUser($user, $projectId);

        $serviceType = $this->dServiceTypeRepo->findBySlug($serviceTypeSlug);

        if (!$form = $this->dServiceDomain->getCreateForm($serviceType)) {
            return new AjaxResponse([
                'type' => AjaxResponse::AJAX_REDIRECT,
                'data' => '',
            ], AjaxResponse::HTTP_BAD_REQUEST);
        }

        $form->fromArray($request->request->all());

        $form->service_name_used = $this->dServiceRepo->findOneBy([
            'project' => $project,
            'name'    => $form->name,
        ]);

        $form->project = $project;
        $form->type    = $serviceType;

        $this->validator->setSource($form);

        if (!$this->validator->isValid()) {
            return new AjaxResponse([
                'type'   => AjaxResponse::AJAX_ERROR,
                'errors' => $this->validator->getErrors(true),
            ], AjaxResponse::HTTP_BAD_REQUEST);
        }

        $this->dServiceDomain->createService($form);

        return new AjaxResponse([
            'type' => AjaxResponse::AJAX_REDIRECT,
            'data' => $this->generateUrl('project.view.get', [
                'projectId' => $project->getId(),
            ]),
        ], AjaxResponse::HTTP_OK);
    }

    /**
     * @Route(name="project.service.view.get",
     *     path="/project/{projectId}/service/{serviceId}",
     *     methods={"GET"}
     * )
     * @param Entity\User $user
     * @param string      $projectId
     * @param string      $serviceId
     * @return Response
     */
    public function getView(
        Entity\User $user,
        string $projectId,
        string $serviceId
    ) : Response {
        if (!$project = $this->dProjectRepo->findByUser($user, $projectId)) {
            return $this->render('@Dashtainer/project/not-found.html.twig');
        }

        if (!$service = $this->dServiceRepo->findByProject($project, $serviceId)) {
            return $this->render('@Dashtainer/project/service/not-found.html.twig', [
                'project' => $project,
            ]);
        }

        $serviceType = $service->getType();
        $template    = sprintf('@Dashtainer/project/service/%s/view.html.twig',
            strtolower($serviceType->getSlug())
        );

        $params = $this->dServiceDomain->getViewParams($service);

        return $this->render($template, array_merge([
            'service'           => $service,
            'project'           => $project,
            'serviceCategories' => $this->dServiceCatRepo->findAll(),
        ], $params));
    }

    /**
     * @Route(name="project.service.update.get",
     *     path="/project/{projectId}/service/{serviceId}/update",
     *     methods={"GET"}
     * )
     * @param Entity\User $user
     * @param string      $projectId
     * @param string      $serviceId
     * @return Response
     */
    public function getUpdate(
        Entity\User $user,
        string $projectId,
        string $serviceId
    ) : Response {
        if (!$project = $this->dProjectRepo->findByUser($user, $projectId)) {
            return $this->render('@Dashtainer/project/not-found.html.twig');
        }

        if (!$service = $this->dServiceRepo->findByProject($project, $serviceId)) {
            return $this->render('@Dashtainer/project/service/not-found.html.twig', [
                'project' => $project,
            ]);
        }

        $nonJoinedNetworks = $this->dNetworkRepo->findByNotService($service);
        $allSecrets        = $this->dSecretRepo->findAllByProject($project);

        $serviceType = $service->getType();
        $template    = sprintf('@Dashtainer/project/service/%s/update.html.twig',
            strtolower($serviceType->getSlug())
        );

        $params = $this->dServiceDomain->getViewParams($service);

        return $this->render($template, array_merge([
            'service'           => $service,
            'project'           => $project,
            'nonJoinedNetworks' => $nonJoinedNetworks,
            'allSecrets'        => $allSecrets,
            'serviceCategories' => $this->dServiceCatRepo->findAll(),
        ], $params));
    }

    /**
     * @Route(name="project.service.update.post",
     *     path="/project/{projectId}/service/{serviceId}/update",
     *     methods={"POST"}
     * )
     * @param Request     $request
     * @param Entity\User $user
     * @param string      $projectId
     * @param string      $serviceId
     * @return AjaxResponse
     */
    public function postUpdate(
        Request $request,
        Entity\User $user,
        string $projectId,
        string $serviceId
    ) : AjaxResponse {
        ;
        if (!$project = $this->dProjectRepo->findByUser($user, $projectId)) {
            return new AjaxResponse([
                'type' => AjaxResponse::AJAX_REDIRECT,
                'data' => '',
            ], AjaxResponse::HTTP_BAD_REQUEST);
        }

        if (!$service = $this->dServiceRepo->findByProject($project, $serviceId)) {
            return new AjaxResponse([
                'type' => AjaxResponse::AJAX_REDIRECT,
                'data' => '',
            ], AjaxResponse::HTTP_BAD_REQUEST);
        }

        $serviceType = $service->getType();

        if (!$form = $this->dServiceDomain->getCreateForm($serviceType)) {
            return new AjaxResponse([
                'type' => AjaxResponse::AJAX_REDIRECT,
                'data' => '',
            ], AjaxResponse::HTTP_BAD_REQUEST);
        }

        $form->fromArray($request->request->all());

        $form->name    = $service->getName();
        $form->project = $project;
        $form->type    = $serviceType;

        $this->validator->setSource($form);

        if (!$this->validator->isValid()) {
            return new AjaxResponse([
                'type'   => AjaxResponse::AJAX_ERROR,
                'errors' => $this->validator->getErrors(true),
            ], AjaxResponse::HTTP_BAD_REQUEST);
        }

        $service = $this->dServiceDomain->updateService($service, $form);

        return new AjaxResponse([
            'type' => AjaxResponse::AJAX_REDIRECT,
            'data' => $this->generateUrl('project.service.view.get', [
                'projectId' => $form->project->getId(),
                'serviceId' => $service->getId(),
            ]),
        ], AjaxResponse::HTTP_OK);
    }

    /**
     * @Route(name="project.service.delete.post",
     *     path="/project/{projectId}/service/{serviceId}/delete",
     *     methods={"POST"}
     * )
     * @param Entity\User $user
     * @param string      $projectId
     * @param string      $serviceId
     * @return AjaxResponse
     */
    public function postDelete(
        Entity\User $user,
        string $projectId,
        string $serviceId
    ) : AjaxResponse {
        if (!$project = $this->dProjectRepo->findByUser($user, $projectId)) {
            return new AjaxResponse([
                'type' => AjaxResponse::AJAX_REDIRECT,
                'data' => $this->generateUrl('project.index.get'),
            ], AjaxResponse::HTTP_OK);
        }

        if (!$service = $this->dServiceRepo->findByProject($project, $serviceId)) {
            return new AjaxResponse([
                'type' => AjaxResponse::AJAX_REDIRECT,
                'data' => $this->generateUrl('project.index.get'),
            ], AjaxResponse::HTTP_OK);
        }

        $this->dServiceDomain->deleteService($service);

        return new AjaxResponse([
            'type' => AjaxResponse::AJAX_REDIRECT,
            'data' => $this->generateUrl('project.index.get'),
        ], AjaxResponse::HTTP_OK);
    }
}
