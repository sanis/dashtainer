<?php

namespace Dashtainer\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Symfony\Component\Yaml\Yaml;

class Version1_0_5 extends FixtureMigrationAbstract
{
    public function up(Schema $schema)
    {
        $serializer = $this->container->get('serializer');
        $kernel     = $this->container->get('kernel');

        $this->addSql('
            ALTER TABLE docker_secret
              ADD file VARCHAR(255) DEFAULT NULL AFTER external 
        ');

        $this->addSql('
            ALTER TABLE docker_secret
              ADD contents LONGTEXT DEFAULT NULL AFTER file;
        ');
    }

    public function down(Schema $schema)
    {
    }

    public function postUp(Schema $schema)
    {
    }
}
