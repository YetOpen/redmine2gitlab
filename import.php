<?php
require_once 'vendor/autoload.php';

$rm2gl = new Redmine2Gitlab();
// Source Redmine identifier - Destination GitLab identifier with namespace (group)

$list = file("projects.txt");
foreach ($list as $proj) {
    list($rmid, $glid) = explode(":", trim($proj));
    $rm2gl->import($rmid, $glid);
}

class Redmine2Gitlab
{
    // List of descriptive names for closed issues on Redmine, used to close the corresponding GL issue
    const CLOSED_STATUS = array("Chiuso", "Rifiutato", "Risolto");

    /** @var Redmine\Client */
    private $rm_client;

    /** @var Gitlab\Client */
    private $gl_client;

    /** @var array */
    private $gl_user_cache;
    /** @var array */
    private $rm_user_cache;
    
    private $pandoc;

    public function __construct()
    {
        require_once 'config.php';
        
        $this->rm_client = new Redmine\Client($redmine_url, $redmine_api);
        //$rm_client = new Redmine\Client('http://redmine.example.com', 'username', 'password');

        $this->gl_client = new \Gitlab\Client($gitlab_url.'/api/v3/');
        $this->gl_client->authenticate($gitlab_token, \Gitlab\Client::AUTH_URL_TOKEN);
        
        $this->gl_user_cache = array ();
        $this->rm_user_cache = array ();
        
        $this->pandoc = new Pandoc\Pandoc();
        
        $this->priority_label = $priority_labels;
    }

    /**
     * Get GitLab's user from an email address
     * @param string $email
     * @return Gitlab\User
     */
    private function getGlUser($email)
    {
        if (!array_key_exists($email, $this->gl_user_cache)) {
            $gl_users = $this->gl_client->api('users')->search($email);
            // if there is no result assign to admin
            if (empty($gl_users)) {
                $gl_users = $this->gl_client->api('users')->search('admin');
            }
            $this->gl_user_cache[$email] = $gl_users[0];
        }
        return $this->gl_user_cache[$email];
    }

    private function getGlUserId($email)
    {
        $u = $this->getGlUser($email);
        return $u['id'];
    }

    private function identifyRmUser($id)
    {
        if (!array_key_exists($id, $this->rm_user_cache)) {
            $rmus = $this->rm_client->api('user')->show($id);
            if (!empty($rmus['user'])) {
                $this->rm_user_cache[$id] = $rmus['user'];
            } else {
                // redmine user not existing anymore
                $this->rm_user_cache[$id] = array('email' => '');
            }
        }
        return $this->rm_user_cache[$id];
    }
    
    /**
     * Searches a GitLab user from a Redmine id
     * @param integer $id
     * @return integer
     */
    private function searchGlUserIdByRmId($id)
    {
        $rm_user = $this->identifyRmUser($id);
        return $this->getGlUserId($rm_user['mail']);
    }

    /**
     * Import project's issues
     * @param string $rm_project_identifier RedMine project identifier 
     * @param string $gl_project_identifier GitLab project identifier (with group)
     */
    public function import($rm_project_identifier, $gl_project_identifier)
    {
        echo "Importing issues for Redmine project {$rm_project_identifier} to GitLab's {$gl_project_identifier} \n";

        $gl_project = new \Gitlab\Model\Project($gl_project_identifier, $this->gl_client);
        foreach ($this->priority_label as $l) {
            try {
                $gl_project->addLabel($l['name'], $l['color']);
            } catch (Gitlab\Exception\RuntimeException $e) {
                // ignore already existing labels
            }
        }

        $project_issues = $this->rm_client->api('issue')->all(array(
            'project_id' => $rm_project_identifier,
            'limit' => 10000, // too lazy to do pagination
        ));
        foreach ($project_issues['issues'] as $issue) {
            $this->importIssue($issue['id'], $gl_project);
        }
        echo "Import finished!\n\n";
    }

    /**
     * Import a single issue
     * @param integer $rm_issue_id
     * @param GitLab\Project $gl_project
     */
    private function importIssue($rm_issue_id, $gl_project)
    {
        $attachment_basepath = 'attachments/'.$gl_project->name.'/';
        $issue_array = $this->rm_client->api('issue')->show($rm_issue_id, array('include' => array('journals', 'attachments')));
        $rm_issue = $issue_array['issue'];
        
        $import_note = "\n\n**Migrated from redmine**  \n  \n"
            ."| RM field | RM value |  \n"
            ."| --- | --- |  \n"
            ."| ID | {$rm_issue['id']} |  \n"
            ."|Created on | {$rm_issue['created_on']} |  \n"
            ."|Status | {$rm_issue['status']['name']} |  \n";
        
        $labels = null;
        if (array_key_exists($rm_issue['priority']['name'], $this->priority_label)) {
            $labels = $this->priority_label[$rm_issue['priority']['name']]['name'];
        }
            
        // Create the new issue
        $gl_issue = $gl_project->createIssue($rm_issue['subject'], array(
            'description' => $this->pandoc->convert($rm_issue['description'], "textile", "markdown_github")
                .$import_note,
            'assignee_id' => $this->searchGlUserIdByRmId($rm_issue['assigned_to']['id']),
            'labels' => $labels,
        ));

        
        if (array_search($rm_issue['status']['name'], self::CLOSED_STATUS))
            $gl_issue->close();

        foreach ($rm_issue['journals'] as $j) {
            if (empty($j['notes']))
                continue;
            $comment = $this->pandoc->convert($j['notes'], "textile", "markdown_github");
            $comment .= "\n\n> By ".$this->identifyRmUser($j['user']['id'])['mail'];
            $comment .= " on ".$rm_issue['created_on'];
            
            $gl_issue->addComment($comment);
        }

        if (!empty($rm_issue['attachments'])) {
            $desc = $gl_project->show();
            $attachment_basepath = 'attachments/'.$desc->name.'/';
            foreach ($rm_issue['attachments'] as $attachment) {
                if (!file_exists($attachment_basepath))
                    mkdir($attachment_basepath);
                // Attachments
                $file_content = $this->rm_client->api('attachment')->download($attachment['id']);
                $attachment_filename = 'GL-'.$gl_issue->iid.'-'.$attachment['filename'];
                file_put_contents($attachment_basepath.$attachment_filename, $file_content);

                $attachment_comment = "> Attachment {$attachment['filename']} ";
                if (!empty($attachment_attrs['description']))
                    $attachment_comment .= "({$attachment_attrs['description']})";
                $attachment_comment .= "saved as '$attachment_filename' ";
                $gl_issue->addComment($attachment_comment);
            }
        }
        
        echo "Imported Redmine issue {$rm_issue['id']} as GitLab's {$gl_issue->iid} \n";
    }
}
