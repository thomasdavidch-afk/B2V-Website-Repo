<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260902142423 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE carnet_session (id INT AUTO_INCREMENT NOT NULL, nb_sessions_total INT NOT NULL, nb_sessions_restant INT NOT NULL, user_id INT NOT NULL, UNIQUE INDEX UNIQ_631EC496A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE certificat_medical (id INT AUTO_INCREMENT NOT NULL, fichier VARCHAR(255) NOT NULL, date_upload DATETIME NOT NULL, date_expiration DATE NOT NULL, statut VARCHAR(50) NOT NULL, user_id INT NOT NULL, UNIQUE INDEX UNIQ_3C705FDBA76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE presence (id INT AUTO_INCREMENT NOT NULL, statut VARCHAR(50) NOT NULL, date_enregistrement DATETIME NOT NULL, user_id INT NOT NULL, session_entrainement_id INT NOT NULL, INDEX IDX_6977C7A5A76ED395 (user_id), INDEX IDX_6977C7A53537A688 (session_entrainement_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE session_entrainement (id INT AUTO_INCREMENT NOT NULL, date_session DATE NOT NULL, heure_debut TIME NOT NULL, heure_fin TIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE transaction (id INT AUTO_INCREMENT NOT NULL, id_hello_asso VARCHAR(100) DEFAULT NULL, type_transaction VARCHAR(100) NOT NULL, nb_sessions INT NOT NULL, montant DOUBLE PRECISION DEFAULT NULL, date_transaction DATETIME NOT NULL, origin VARCHAR(50) NOT NULL, user_id INT NOT NULL, INDEX IDX_723705D1A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE carnet_session ADD CONSTRAINT FK_631EC496A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE certificat_medical ADD CONSTRAINT FK_3C705FDBA76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE presence ADD CONSTRAINT FK_6977C7A5A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE presence ADD CONSTRAINT FK_6977C7A53537A688 FOREIGN KEY (session_entrainement_id) REFERENCES session_entrainement (id)');
        $this->addSql('ALTER TABLE transaction ADD CONSTRAINT FK_723705D1A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE carnet_session DROP FOREIGN KEY FK_631EC496A76ED395');
        $this->addSql('ALTER TABLE certificat_medical DROP FOREIGN KEY FK_3C705FDBA76ED395');
        $this->addSql('ALTER TABLE presence DROP FOREIGN KEY FK_6977C7A5A76ED395');
        $this->addSql('ALTER TABLE presence DROP FOREIGN KEY FK_6977C7A53537A688');
        $this->addSql('ALTER TABLE transaction DROP FOREIGN KEY FK_723705D1A76ED395');
        $this->addSql('DROP TABLE carnet_session');
        $this->addSql('DROP TABLE certificat_medical');
        $this->addSql('DROP TABLE presence');
        $this->addSql('DROP TABLE session_entrainement');
        $this->addSql('DROP TABLE transaction');
    }
}
