========
Overview
========

This module permits API comunication with https://translations.com.es

Installation
------------
Checkout a copy of the code::

    // in composer.json
    "repositories": [
        // ...
        {
            "type": "vcs",
            "url": "https://github.com/jlaso/translations-api-connector.git"
        }
        // ...
    ],
    "require": {
        // ...
        "jlaso/translations-api-connector": "*"
        // ...
    },


Then register the bundle with your kernel::

    // in app/console
    use JLaso\TranslationsApiBundle\Command\TranslationsExtractCommand;
    use JLaso\TranslationsApiBundle\Command\TranslationsHelperCommand;
    use JLaso\TranslationsApiBundle\Command\TranslationsSyncDocumentsCommand;
    use JLaso\TranslationsApiBundle\Command\TranslationsSyncCommand;

    // ...
    $application->add(new TranslationsExtractCommand);
    $application->add(new TranslationsHelperCommand);
    $application->add(new TranslationsSyncDocumentsCommand);
    $application->add(new TranslationsSyncCommand);
    // ...


Configuration
-------------
::

    // in app/config/parameters.yml
    ###############################
    ##   TRANSLATIONS API REST   ##
    ###############################
    jlaso_translations_api_access:
        project_id: x      # the number that correspond to the project created
        key:  my-key       # the key that systems assigns
        secret: my-secret  # the password that you choose when init project in server
        url: https://translations.com.es/api/



Examples
--------
For synchronize local translations with server (remote) translations:
::
    app/console jlaso:translations:sync



