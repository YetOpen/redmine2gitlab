<?php

$redmine_url = "http://redmine.domain.com";
$redmine_api = ""; // an admin api key from profile
$gitlab_url = "http://gitlab.domain.com"; // base url, no ending /api/v3
$gitlab_token = ""; // an admin api key from profile

// Example for translating Redmine's priorities to labels in GitLab
// Labels will be created if missing
$priority_labels = array (
    'Bassa' => array ('name' => 'pri:bassa', 'color' => '#a7a7a7'),
    'Alta' => array ('name' => 'pri:alta', 'color' => '#fffc00'),
    'Urgente' => array ('name' => 'pri:urgente', 'color' => '#ffa800'),
    'Immediata' => array ('name' => 'pri:immediata', 'color' => '#ff0000'),
);