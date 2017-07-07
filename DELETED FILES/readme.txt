######################################
# Bitcoin NO API Requried            #
#     Open Shopping Cart             #
######################################

I have been receiving a lot of reports that users are unable to obtain API Keys from Blockchain.info. 
So I have created this shopping cart that will allow users to accept bitcoin on their website without having to use a privileged API service. 
This project does use some public API's to check the blockchain but they require no permission or API Keys. 

View Demo: btcthreads.com/testshop

WARNING: THIS PROJECT STORES PRIVATE KEYS ON YOUR SERVER!!! ITS INTENDED USE IS FOR SMALL VALUE ITEMS. YOU ARE ENCOURAGED TO SWEEP KEYS THROUGH THE ADMIN PANEL AS SOON AS POSSIBLE AFTER A SALE TO REDUCE POTENTIAL RISK. 

This is a fork of a previous project, Blockchain Receive Payments API Shopping Cart (https://github.com/coinables/Blockchain-Receive-Payments-API-Shopping-Cart)

1. Download the files in the repository
2. Create and configure a database on your webserver (How to: http://www.fastcomet.com/tutorials/cpanel/create-database)
3. Import the included SQL database files using PHPmyadmin or similar database manager (How to: https://www.namecheap.com/support/knowledgebase/article.aspx/9143/29/how-to-import-and-export-database-in-cpanel-access-denied-create-database-dbname-error-and-how-to-fix-it
4. Open the config.php file and update the fields

That's it!

Access the admin panel to manage your orders and products with login.php
The password to access the admin page is set in your config.php
