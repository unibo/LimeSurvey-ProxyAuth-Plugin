# UniboGroupsAuth - LimeSurvey Plugin
A simple LimeSurvey plugin to manage both admin and closed survey participants authorization based on header groups info.

**Author**: Matteo Parrucci  
**Email**: m.parrucci@unibo.it  
**Website**: https://www.github.com/unibo  
**Licence**: BSD 3-Clause  
**Licence Owner**: Bologna University (https://www.unibo.it/it)  
**LimeSurvey compatibility**: 4.X , 5.X

## Description:
Limesurvey allows editors to setup two kinds of surveys:
- **Open surveys** that allows everybody that knows the web address to fill the survey
- **Closed surveys** that allow only invited users to fill the survey. Limesurvey gives the tools to invite users by mail assigning them a unique token

This plugin allows every user that has some configurable groups in the request header to fill **closed** surveys.  
It also decides based on the same groups contained in the headers, the admin rights of the user.  
These headers should obviously come from a trusted authentication extension.

### How it works:
This plugin manages two distinct types of authorization in LimeSurvey: 
- Administrative authorization: this type of authorization checks that the user belongs to one of the groups enabled for one of the roles (admin, servicedesk and editor). if the user does not exist, he creates it and assigns it the correct role based on the group. In case of admin role the permissions are given directly to the user because they are not supported by roles. The roles are set with default permissions at the first activation of the plugin. The default permissions are as follows:
    - admin that has all rights
    - servicedesk that can CRUD users, labelsets, surveysgroups and surveys and R participantpanel and settings.
    - editor that can U surveys and R labelses
- Survey authorization: This type of authorization is used to allow users to fill in closed surveys. In this case, our plugin checks if the user belongs to one of the groups enabled for the specific survey and can be set in the survey settings. If so, it creates a survey "participant" with a token and redirects it, with the token, to the fill-in page.

## Headers structure:
The only required headers to make the authorization process work are X-Remote-User and X-Remote-Groups.  
Headers could, and in our case are, added by the proxy after verifying the user is authenticated using an apache plugin.

## How to test this plugin:
In production you will better have some authentication in place on the proxy and add the required headers in order to make it work but in development you can also use a chrome extension like ModHeader that let you set your own headers.  
N.B. In production, to avoid people filling the surveys using such extension, it is required to remove those headers from requests before running the auth plugin that eventually adds them back with the right contents.

## Plugin configurations:
![Plugin configurations](docs/screenshots/Screenshot1.png)  
- **Key to use for username**: Header key to check in $_SERVER to retrive the username (defaults to HTTP_X_REMOTE_USER)  
- **Key to use for email**: Header key to check in $_SERVER to retrive the email (defaults to HTTP_X_REMOTE_EMAIL)  
- **Key to use for groups**: Header key to check in $_SERVER to retrive the comma separated groups (defaults to HTTP_X_REMOTE_GROUPS)  
- **Key to use for first name**: Header key to check in $_SERVER to retrive the first name (defaults to HTTP_X_REMOTE_FIRSTNAME)  
- **Key to use for last name**: Header key to check in $_SERVER to retrive the last name (deafults to HTTP_X_REMOTE_LASTNAME)  
- **Key to use for language**: Header key to check in $_SERVER to retrive the user language (defaults to HTTP_X_REMOTE_LANGUAGE)  
- **Administrator groups**: Comma separated list of groups to accept as administators. (defaults to limesurvey_admin)  
- **ServiceDesk groups**: Comma separated list of groups to accept as service desk. (defaults to limesurvey_servicedesk)  
- **Editor groups**: Comma separated list of groups to accept as editors. (defaults to limesurvey_editor)  

## Survey additional configuration:
![Survey configuration](docs/screenshots/Screenshot2.png)  
- **Group names**: The comma separated list of allowed groups to fill this specific survey (defaults to empty). If empty it will always ask for a token.

## Installation instructions:
- Clone this repository inside the plugin directory of your LimeSurvey installation

## Docker
This plugin also contains a docker folder. We used docker-compose for development and the stack is made of nginx -> php-fpm -> mariadb.  
You can find all the settings inside the .env.template; please remember to change the database password if using on production.  

### Installation:
To use this docker-compose:  
- Create a foldet with the name of your project where you prefere  
- Download latest limesurvey version in a folder named www (or the way you like modifying PHP_DIRECTORY and WEB_DIRECTORY)  
- git clone this repository inside the www/plugins subdirectory  
- ln -s www/plugins/UniboGroupsAuth/docker-compose.yml inside your project folder  
- ln -s www/plugins/UniboGroupsAuth/config inside your project folder  
- cp www/plugins/UniboGroupsAuth/.env.template inside your project folder and rename it .env  
- Edit the .env file and modify the environment variable you want to change (Please remember to change the DB passwords)  
- mkdir www/tmp/sessions; chown www-data www/ -R  
- launch docker-compose up -d from inside your project folder  
- go to the selected 

### Reverse proxying:
If you are planning to use nginx as reverse proxy you can use nginx.external.conf as a starting point as follows  
- cp config/nginx.external.conf /etc/nginx/sites-available/HOSTNAME.conf (replace HOSTNAME with your hostname)
- edit /etc/nginx/sites-available/HOSTNAME.conf to your taste
- ln -s /etc/nginx/sites-available/HOSTNAME.conf /etc/nginx/sites-enabled/HOSTNAME.conf
If you are planning to use other means all you need to know is that php-fpm is listening on 127.0.0.1:7000

### Dependencies:
This plugin does not have particular dependencies. Well You must have LimeSurvey and an architecture to serve it.

## Repository structure:
The repository structure is self-explanatory; there is:
- a docker folder containing the docker-compose file and a config folder containing the docker configuration files
- a docs folder containing the readme screenshots
- a composer.json file that contains the plugin info in a machine readable format
- a config.xml file that contains the informations used by LimeSurvey in the plugin page
- a LICENCE file containg the full text of the licence
- a UniboGroupsAuth.php file that contains the plugin code
- this README.md file

## Techincal Notes: 
- This plugin only does its magic on survey with specified participants, it does nothing in open participants surveys.
- This plugin dynamically adds a new participants to the survey participant table and assigns them a token before redirecting to the survey with the needed token in querystring.
- This plugin dinamically adds platform users with admin permissions based on the groups.

### Project status:
Development

### Issues:
For general issues please feel free to use this repo issue tracker (https://github.com/unibo/LimeSurvey-ProxyAuth-Plugin/issues)
For security reports please use the email reported on top of this readme in order to avoid public disclosure of an exploitable vulnerability until fixed.
