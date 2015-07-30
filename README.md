Redmine issue exporter to GitLab
================================

This is a basic issue importer from Redmine to GitLab


Requirements
------------

PanDoc is required for converting markdown. See [here](https://github.com/ryakad/pandoc-php#installation) for installation instructions.

[Composer](http://getcomposer.org) is used for dependencies.


Installation
------------

First run `composer update` to install PHP deps. Then copy `config-sample.php` to `config.php` and adjust it
to fit your environment.

Running
-------

Create a file called `projects.txt` in the same path as the file. The file will contain association of Redmine projects and Gitlab ones.
For example

    remine-project:namespace/gitlab-project

Then run:

`php import.php`


Limitations
-----------

* Currently GitLab's API doesn't allow file upload. So files are stored in directory `attachments`, in another
subdirectory with project's name.

