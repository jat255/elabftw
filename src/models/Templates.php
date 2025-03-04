<?php declare(strict_types=1);
/**
 * @author Nicolas CARPi <nico-git@deltablot.email>
 * @copyright 2012 Nicolas CARPi
 * @see https://www.elabftw.net Official website
 * @license AGPL-3.0
 * @package elabftw
 */

namespace Elabftw\Models;

use Elabftw\Enums\BasePermissions;
use Elabftw\Enums\EntityType;
use Elabftw\Enums\State;
use Elabftw\Services\Filter;
use Elabftw\Services\UsersHelper;
use Elabftw\Traits\SortableTrait;
use PDO;

/**
 * All about the templates
 */
class Templates extends AbstractTemplateEntity
{
    use SortableTrait;

    public const defaultBody = '<h1>Goal:</h1>
    <p>&nbsp;</p>
    <h1>Procedure:</h1>
    <p>&nbsp;</p>
    <h1>Results:</h1>
    <p>&nbsp;</p>';

    public const defaultBodyMd = "# Goal\n\n# Procedure\n\n# Results\n\n";

    public function __construct(Users $users, ?int $id = null)
    {
        $this->type = EntityType::Templates->value;
        parent::__construct($users, $id);
    }

    public function getPage(): string
    {
        return 'api/v2/experiments_templates/';
    }

    public function create(string $title): int
    {
        $title = Filter::title($title);
        $canread = BasePermissions::MyTeams->toJson();
        $canwrite = BasePermissions::User->toJson();

        if (isset($this->Users->userData['default_read'])) {
            $canread = $this->Users->userData['default_read'];
        }
        if (isset($this->Users->userData['default_write'])) {
            $canwrite = $this->Users->userData['default_write'];
        }

        $sql = 'INSERT INTO experiments_templates(team, title, userid, canread, canwrite)
            VALUES(:team, :title, :userid, :canread, :canwrite)';
        $req = $this->Db->prepare($sql);
        $req->bindParam(':team', $this->Users->userData['team'], PDO::PARAM_INT);
        $req->bindValue(':title', Filter::title($title));
        $req->bindParam(':userid', $this->Users->userData['userid'], PDO::PARAM_INT);
        $req->bindParam(':canread', $canread);
        $req->bindParam(':canwrite', $canwrite);
        $req->execute();
        $id = $this->Db->lastInsertId();

        // now pin the newly created template so it directly appears in Create menu
        $this->setId($id);
        $Pins = new Pins($this);
        $Pins->togglePin();
        return $id;
    }

    /**
     * Duplicate a template from someone else in the team
     */
    public function duplicate(): int
    {
        $template = $this->readOne();

        $sql = 'INSERT INTO experiments_templates(team, title, body, userid, canread, canwrite, metadata)
            VALUES(:team, :title, :body, :userid, :canread, :canwrite, :metadata)';
        $req = $this->Db->prepare($sql);
        $req->bindParam(':team', $this->Users->userData['team'], PDO::PARAM_INT);
        $req->bindParam(':title', $template['title']);
        $req->bindParam(':body', $template['body']);
        $req->bindParam(':userid', $this->Users->userData['userid'], PDO::PARAM_INT);
        $req->bindParam(':canread', $template['canread']);
        $req->bindParam(':canwrite', $template['canwrite']);
        $req->bindParam(':metadata', $template['metadata']);
        $req->execute();
        $newId = $this->Db->lastInsertId();

        // copy tags
        $Tags = new Tags($this);
        $Tags->copyTags($newId);

        // copy links and steps too
        $ItemsLinks = new ItemsLinks($this);
        $ItemsLinks->duplicate((int) $template['id'], $newId, true);
        $Steps = new Steps($this);
        $Steps->duplicate((int) $template['id'], $newId, true);

        return $newId;
    }

    public function readOne(): array
    {
        $sql = "SELECT experiments_templates.id, experiments_templates.title, experiments_templates.body,
            experiments_templates.created_at, experiments_templates.modified_at, experiments_templates.content_type,
            experiments_templates.userid, experiments_templates.canread, experiments_templates.canwrite,
            experiments_templates.locked, experiments_templates.lockedby, experiments_templates.locked_at,
            CONCAT(users.firstname, ' ', users.lastname) AS fullname, experiments_templates.metadata, experiments_templates.state,
            users.firstname, users.lastname, users.orcid,
            GROUP_CONCAT(tags.tag SEPARATOR '|') AS tags, GROUP_CONCAT(tags.id) AS tags_id
            FROM experiments_templates
            LEFT JOIN users ON (experiments_templates.userid = users.userid)
            LEFT JOIN tags2entity ON (experiments_templates.id = tags2entity.item_id AND tags2entity.item_type = 'experiments_templates')
            LEFT JOIN tags ON (tags2entity.tag_id = tags.id)
            WHERE experiments_templates.id = :id";
        $req = $this->Db->prepare($sql);
        $req->bindParam(':id', $this->id, PDO::PARAM_INT);
        $this->Db->execute($req);
        $this->entityData = $this->Db->fetch($req);
        $this->canOrExplode('read');
        // add steps and links in there too
        $this->entityData['steps'] = $this->Steps->readAll();
        $this->entityData['items_links'] = $this->ItemsLinks->readAll();
        return $this->entityData;
    }

    /**
     * Filter the readable templates to only get the ones where we can write to
     * Use this to display templates in UCP
     */
    public function getWriteableTemplatesList(): array
    {
        $TeamGroups = new TeamGroups($this->Users);
        $teamgroupsOfUser = array_column($TeamGroups->readGroupsFromUser(), 'id');

        return array_filter($this->readAll(), function ($t) use ($teamgroupsOfUser) {
            $canwrite = json_decode($t['canwrite'], true, 3, JSON_THROW_ON_ERROR);
            return $canwrite['base'] === BasePermissions::Full->value || $canwrite['base'] === BasePermissions::Organization->value ||
                ($canwrite['base'] === BasePermissions::MyTeams->value && ((int) $t['teams_id'] === $this->Users->userData['team'])) ||
                ($canwrite['base'] === BasePermissions::User->value && $t['userid'] === $this->Users->userData['userid']) ||
                ($canwrite['base'] === BasePermissions::UserOnly->value && $t['userid'] === $this->Users->userData['userid']) ||
                (in_array($canwrite['teamgroups'], $teamgroupsOfUser, true));
        });
    }

    /**
     * Get a list of fullname + id + title of template
     * Use this to build a select of the readable templates
     */
    public function readAll(): array
    {
        $TeamGroups = new TeamGroups($this->Users);
        $teamgroupsOfUser = array_column($TeamGroups->readGroupsFromUser(), 'id');

        $sql = sprintf("SELECT DISTINCT experiments_templates.id, experiments_templates.title, experiments_templates.body,
                experiments_templates.userid, experiments_templates.canread, experiments_templates.canwrite, experiments_templates.content_type,
                experiments_templates.locked, experiments_templates.lockedby, experiments_templates.locked_at,
                CONCAT(users.firstname, ' ', users.lastname) AS fullname, experiments_templates.metadata,
                users2teams.teams_id, teams.name AS team_name,
                (pin_experiments_templates2users.entity_id IS NOT NULL) AS is_pinned,
                GROUP_CONCAT(tags.tag SEPARATOR '|') AS tags, GROUP_CONCAT(tags.id) AS tags_id
                FROM experiments_templates
                LEFT JOIN users ON (experiments_templates.userid = users.userid)
                LEFT JOIN users2teams ON (users2teams.users_id = users.userid AND users2teams.teams_id = :team)
                LEFT JOIN teams ON (teams.id = experiments_templates.team)
                LEFT JOIN tags2entity ON (experiments_templates.id = tags2entity.item_id AND tags2entity.item_type = 'experiments_templates')
                LEFT JOIN tags ON (tags2entity.tag_id = tags.id)
                LEFT JOIN pin_experiments_templates2users ON (experiments_templates.id = pin_experiments_templates2users.entity_id AND pin_experiments_templates2users.users_id = :userid)
                WHERE experiments_templates.userid != 0 AND experiments_templates.state = :state AND (
                    (JSON_EXTRACT(experiments_templates.canread, '$.base') = %d) OR
                    (JSON_EXTRACT(experiments_templates.canread, '$.base') = %d) OR
                    (JSON_EXTRACT(experiments_templates.canread, '$.base') = %d AND users2teams.users_id = experiments_templates.userid) OR
                    (JSON_EXTRACT(experiments_templates.canread, '$.base') = %d AND experiments_templates.userid = :userid) OR
                    (JSON_EXTRACT(experiments_templates.canread, '$.base') = %d AND experiments_templates.userid = :userid)", BasePermissions::Full->value, BasePermissions::Organization->value, BasePermissions::MyTeams->value, BasePermissions::User->value, BasePermissions::UserOnly->value);
        // look for teams
        $UsersHelper = new UsersHelper((int) $this->Users->userData['userid']);
        $teamsOfUser = $UsersHelper->getTeamsIdFromUserid();
        foreach ($teamsOfUser as $team) {
            $sql .= sprintf(' OR (%d MEMBER OF (experiments_templates.canread->>"$.teams"))', $team);
        }
        // look for teamgroups
        if (!empty($teamgroupsOfUser)) {
            foreach ($teamgroupsOfUser as $teamgroup) {
                $sql .= sprintf(' OR (%d MEMBER OF (experiments_templates.canread->>"$.teamgroups"))', $teamgroup);
            }
        }
        // look for our userid in users part of the json
        $sql .= ' OR (:userid MEMBER OF (experiments_templates.canread->>"$.users"))';
        $sql .= ')';

        $sql .= $this->filterSql;

        $sql .= str_replace('entity', 'experiments_templates', $this->idFilter) . ' ';

        $sql .= 'GROUP BY id ORDER BY fullname DESC, is_pinned DESC, experiments_templates.ordering ASC';

        $req = $this->Db->prepare($sql);
        $req->bindParam(':team', $this->Users->userData['team'], PDO::PARAM_INT);
        $req->bindParam(':userid', $this->Users->userData['userid'], PDO::PARAM_INT);
        $req->bindValue(':state', State::Normal->value, PDO::PARAM_INT);
        $this->Db->execute($req);

        return $req->fetchAll();
    }

    /**
     * Read the templates for the user (in ucp or create new menu)
     * depending on the user preference, we filter out on the owner or not
     */
    public function readForUser(): array
    {
        if ($this->Users->userData['show_team_templates'] === 0) {
            $this->addFilter('experiments_templates.userid', $this->Users->userData['userid']);
        }
        return $this->readAll();
    }

    public function destroy(): bool
    {
        // delete from pinned too
        return parent::destroy() && $this->Pins->cleanup();
    }
}
