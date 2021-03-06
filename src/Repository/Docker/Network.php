<?php

namespace Dashtainer\Repository\Docker;

use Dashtainer\Entity;
use Dashtainer\Repository;

use Doctrine\ORM;
use Doctrine\Common\Persistence;

class Network implements Repository\ObjectPersistInterface
{
    protected const ENTITY_CLASS = Entity\Docker\Network::class;

    /** @var ORM\EntityManagerInterface */
    protected $em;

    /** @var Persistence\ObjectRepository */
    protected $repo;

    public function __construct(ORM\EntityManagerInterface $em)
    {
        $this->em   = $em;
        $this->repo = $em->getRepository(self::ENTITY_CLASS);
    }

    /**
     * @inheritdoc
     * @return Entity\Docker\Network|null
     */
    public function find($id) : ?Entity\Docker\Network
    {
        return $this->repo->find($id);
    }

    /**
     * @inheritdoc
     * @return Entity\Docker\Network[]
     */
    public function findAll() : array
    {
        return $this->repo->findAll();
    }

    /**
     * @inheritdoc
     * @return Entity\Docker\Network[]
     */
    public function findBy(
        array $criteria,
        array $orderBy = null,
        $limit = null,
        $offset = null
    ) : array {
        return $this->repo->findBy($criteria, $orderBy, $limit, $offset);
    }

    /**
     * @inheritdoc
     * @return Entity\Docker\Network|null
     */
    public function findOneBy(array $criteria) : ?Entity\Docker\Network
    {
        return $this->repo->findOneBy($criteria);
    }

    public function save(object ...$entity)
    {
        foreach ($entity as $ent) {
            $this->em->persist($ent);
        }

        $this->em->flush();
    }

    public function delete(object ...$entity)
    {
        foreach ($entity as $ent) {
            $this->em->remove($ent);
        }

        $this->em->flush();
    }

    public function getClassName() : string
    {
        return self::ENTITY_CLASS;
    }

    /**
     * @param Entity\Docker\Project $project
     * @return Entity\Docker\Network[]
     */
    public function findAllByProject(Entity\Docker\Project $project) : array
    {
        return $this->repo->findBy(['project' => $project]);
    }

    public function findByProject(
        Entity\Docker\Project $project,
        string $id
    ) : ?Entity\Docker\Network {
        return $this->findOneBy([
            'id'      => $id,
            'project' => $project,
        ]);
    }

    /**
     * @param Entity\Docker\Service $service
     * @param bool                  $public
     * @return Entity\Docker\Network[]
     */
    public function findByService(Entity\Docker\Service $service, bool $public = false) : array
    {
        $qb = $this->em->createQueryBuilder()
            ->select('n')
            ->from('Dashtainer:Docker\Network', 'n')
            ->where(':service MEMBER OF n.services')
            ->andWhere('n.project = :project');

        if (!$public) {
            $qb->andWhere('n.is_public = 0');
        }

        $qb->setParameters([
            'service' => $service,
            'project' => $service->getProject(),
        ]);

        return $qb->getQuery()->getResult();
    }

    /**
     * @param Entity\Docker\Service $service
     * @param bool                  $public
     * @return Entity\Docker\Network[]
     */
    public function findByNotService(Entity\Docker\Service $service, bool $public = false) : array
    {
        $qb = $this->em->createQueryBuilder()
            ->select('n')
            ->from('Dashtainer:Docker\Network', 'n')
            ->where(':service NOT MEMBER OF n.services')
            ->andWhere('n.project = :project');

        if (!$public) {
            $qb->andWhere('n.is_public = 0');
        }

        $qb->setParameters([
            'service' => $service,
            'project' => $service->getProject(),
        ]);

        return $qb->getQuery()->getResult();
    }

    public function getPublicNetwork(
        Entity\Docker\Project $project
    ) : ?Entity\Docker\Network {
        return $this->findOneBy([
            'project'   => $project,
            'is_public' => true,
        ]);
    }

    /**
     * @param Entity\Docker\Project $project
     * @return Entity\Docker\Network[]
     */
    public function getPrivateNetworks(
        Entity\Docker\Project $project
    ) : array {
        return $this->findBy([
            'project'     => $project,
            'is_public'   => false,
            'is_editable' => true,
        ]);
    }
}
