<?php

use portalium\workspace\Module;
use yii\db\Migration;

class m261018_000040_workspace_invitation_token_per_email extends Migration
{
    public function safeUp()
    {
        $invitationTable = Module::$tablePrefix . 'invitation';
        $roleTable = Module::$tablePrefix . 'invitation_role';
        if ($this->db->getTableSchema($invitationTable, true) === null || $this->db->getTableSchema($roleTable, true) === null) {
            return true;
        }
        $invitation = $this->db->quoteTableName($invitationTable);
        $role = $this->db->quoteTableName($roleTable);
        $shared = "SELECT id_invitation, MIN(id_invitation_role) AS keep_role FROM $role"
            . " GROUP BY id_invitation HAVING COUNT(DISTINCT LOWER(TRIM(email))) > 1";

        $this->execute(
            "INSERT INTO $invitation (id_workspace, invitation_token, id_user, date_create, date_expire)"
            . " SELECT i.id_workspace, CONCAT('split-', i.id_invitation, '-', MD5(e.email)), i.id_user, i.date_create, i.date_expire"
            . " FROM $invitation i"
            . " JOIN (SELECT id_invitation, LOWER(TRIM(email)) AS email, MIN(id_invitation_role) AS first_role"
            . " FROM $role GROUP BY id_invitation, LOWER(TRIM(email))) e ON e.id_invitation = i.id_invitation"
            . " JOIN ($shared) m ON m.id_invitation = i.id_invitation"
            . " WHERE e.first_role <> m.keep_role"
        );
        $this->execute("UPDATE $invitation i JOIN ($shared) m ON m.id_invitation = i.id_invitation SET i.invitation_token = ''");
        $this->execute(
            "UPDATE $role r JOIN $invitation n"
            . " ON n.invitation_token = CONCAT('split-', r.id_invitation, '-', MD5(LOWER(TRIM(r.email))))"
            . " SET r.id_invitation = n.id_invitation"
        );
        $this->execute(
            "UPDATE $invitation SET invitation_token = ''"
            . " WHERE invitation_token LIKE 'split-%' AND CHAR_LENGTH(invitation_token) > 32"
        );

        return true;
    }

    public function safeDown()
    {
        return true;
    }
}
