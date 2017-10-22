<?php
# Include the Autoloader (see "Libraries" for install instructions)
require '../vendor/autoload.php';
use Mailgun\Mailgun;
require './config.php';
# Instantiate the client.

$mgClient = new Mailgun($config['mailgun']['key']);
$domain = $config['mailgun']['domain'];

# Make the call to the client.
$result = $mgClient->sendMessage($domain,
          array('from'    => 'Mailgun Sandbox <postmaster@mg.nicholware.co.uk>',
                'to'      => 'Aidan Joseph Nichol <aidan@nicholware.co.uk>',
                'subject' => 'Hello Aidan Joseph Nichol',
                'text'    => 'Congratulations Aidan Joseph Nichol, you just sent another email with Mailgun!  You are truly awesome! '));
echo '<pre>';
var_dump($result);
echo '</pre>';
# You can see a record of this email in your logs: https://mailgun.com/app/logs .

# You can send up to 300 emails/day from this sandbox server.
# Next, you should add your own domain so you can send 10,000 emails/month for free.
