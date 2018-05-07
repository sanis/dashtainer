<?php

namespace Dashtainer\Migrations;

use Dashtainer\Domain\Docker\ServiceWorker as Worker;
use Dashtainer\Form\Docker\Service as Form;
use Dashtainer\Entity\Docker as Entity;
use Dashtainer\Repository\Docker as Repository;

use Doctrine\DBAL\Schema\Schema;
use Symfony\Component\Yaml\Yaml;

class Version1_0_5 extends FixtureMigrationAbstract
{
    public function up(Schema $schema)
    {
        $this->addSql('
            CREATE TABLE docker_service_secret (
                id VARCHAR(8) NOT NULL,
                project_secret_id VARCHAR(8) DEFAULT NULL,
                service_id VARCHAR(8) NOT NULL,
                target VARCHAR(64) NOT NULL,
                uid VARCHAR(32) NOT NULL,
                gid VARCHAR(32) NOT NULL,
                mode VARCHAR(4) NOT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                INDEX IDX_7CB10CD762B29D0 (project_secret_id),
                INDEX IDX_7CB10CDED5CA9E6 (service_id),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB;
        ');

        $this->addSql('
            ALTER TABLE docker_service_secret
                ADD CONSTRAINT FK_7CB10CD762B29D0
                FOREIGN KEY (project_secret_id) REFERENCES docker_secret (id);
        ');

        $this->addSql('
            ALTER TABLE docker_service_secret
                ADD CONSTRAINT FK_7CB10CDED5CA9E6
                FOREIGN KEY (service_id) REFERENCES docker_service (id);
        ');

        $this->addSql('
            ALTER TABLE docker_secret
                ADD file VARCHAR(255) DEFAULT NULL AFTER external,
                ADD owner_id VARCHAR(8) DEFAULT NULL AFTER project_id;
        ');

        $this->addSql('
            ALTER TABLE docker_secret
              ADD contents LONGTEXT DEFAULT NULL AFTER file;
        ');

        $this->addSql('
            ALTER TABLE docker_secret
                ADD CONSTRAINT FK_BBB588937E3C61F9
                FOREIGN KEY (owner_id) REFERENCES docker_service (id);
        ');

        $this->addSql('
            CREATE INDEX IDX_BBB588937E3C61F9
                ON docker_secret (owner_id);
        ');

        $this->addSql('
            DROP TABLE docker_services_secrets;
        ');

        $em = $this->container->get('doctrine.orm.entity_manager');

        /** @var Repository\Service $serviceRepo */
        $serviceRepo     = $em->getRepository(Entity\Service::class);
        /** @var Repository\Network $networkRepo */
        $networkRepo     = $em->getRepository(Entity\Network::class);
        /** @var Repository\Secret $secretRepo */
        $secretRepo      = $em->getRepository(Entity\Secret::class);
        /** @var Repository\ServiceType $serviceTypeRepo */
        $serviceTypeRepo = $em->getRepository(Entity\ServiceType::class);

        $this->migrateMariaDB($serviceRepo, $networkRepo, $secretRepo, $serviceTypeRepo);
    }

    public function down(Schema $schema)
    {
    }

    public function postUp(Schema $schema)
    {
    }

    protected function migrateMariaDB(
        Repository\Service $serviceRepo,
        Repository\Network $networkRepo,
        Repository\Secret $secretRepo,
        Repository\ServiceType $serviceTypeRepo
    ) {
        $em = $this->container->get('doctrine.orm.entity_manager');
        $conn = $em->getConnection();
        $stmt = $conn->query("
            SELECT
                ds.project_id project_id,
                ds.id service_id,
                ds.name service_name,
                ds.environment
            FROM docker_service ds
            JOIN docker_service_type dst ON ds.service_type_id = dst.id
            WHERE dst.slug = 'mariadb'
              AND ds.environment IS NOT NULL
        ");

        foreach ($stmt->fetchAll() as $row) {
            $env  = json_decode($row['environment'], true);
            $slug = Transliterator::urlize($row['service_name']);

            $conn->insert('docker_secret', [
                'project_id' => $row['project_id'],
                'owner_id'   => $row['service_id'],
                'name'       => "{$slug}-mysql_host",
                'file'       => "./secrets/{$slug}-mysql_host",
                'contents'   => $slug,
                'created_at' => (new \DateTime())->format(\DateTime::ATOM),
                'updated_at' => (new \DateTime())->format(\DateTime::ATOM),
            ]);

            $conn->insert('docker_secret', [
                'project_id' => $row['project_id'],
                'owner_id'   => $row['service_id'],
                'name'       => "{$slug}-mysql_root_password",
                'file'       => "./secrets/{$slug}-mysql_root_password",
                'contents'   => $env['MYSQL_ROOT_PASSWORD'],
                'created_at' => (new \DateTime())->format(\DateTime::ATOM),
                'updated_at' => (new \DateTime())->format(\DateTime::ATOM),
            ]);

            $conn->insert('docker_secret', [
                'project_id' => $row['project_id'],
                'owner_id'   => $row['service_id'],
                'name'       => "{$slug}-mysql_database",
                'file'       => "./secrets/{$slug}-mysql_database",
                'contents'   => $env['MYSQL_DATABASE'],
                'created_at' => (new \DateTime())->format(\DateTime::ATOM),
                'updated_at' => (new \DateTime())->format(\DateTime::ATOM),
            ]);

            $conn->insert('docker_secret', [
                'project_id' => $row['project_id'],
                'owner_id'   => $row['service_id'],
                'name'       => "{$slug}-mysql_user",
                'file'       => "./secrets/{$slug}-mysql_user",
                'contents'   => $env['MYSQL_USER'],
                'created_at' => (new \DateTime())->format(\DateTime::ATOM),
                'updated_at' => (new \DateTime())->format(\DateTime::ATOM),
            ]);

            $conn->insert('docker_secret', [
                'project_id' => $row['project_id'],
                'owner_id'   => $row['service_id'],
                'name'       => "{$slug}-mysql_password",
                'file'       => "./secrets/{$slug}-mysql_password",
                'contents'   => $env['MYSQL_PASSWORD'],
                'created_at' => (new \DateTime())->format(\DateTime::ATOM),
                'updated_at' => (new \DateTime())->format(\DateTime::ATOM),
            ]);
        }

        $mariaDb = new Worker\MariaDB($serviceRepo, $networkRepo, $secretRepo, $serviceTypeRepo);
        $mariaDbForm = new Form\MariaDBCreate();
        //$mariaDbForm->

        $mariaDbReflectionMethod = new \ReflectionMethod(Worker\MariaDB::class, 'createSecrets');

        $a = '';
    }
}
