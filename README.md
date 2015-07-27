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



Limitations
-----------

* Currently GitLab's API doesn't allow file upload. So files are stored in directory `attachments`, in another
subdirectory with project's name.

