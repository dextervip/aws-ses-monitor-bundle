<?php
namespace SerendipityHQ\Bundle\AwsSesMonitorBundle\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * {@inheritdoc}
 */
class SnsSetupComplaintsTopicCommand extends SnsSetupCommandAbstract
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setDescription(
            'Registers SNS Topic, attaches it to chosen identities as bounce topic and subscribes endpoint to receive bounce notifications'
        );
        $this->setName('awssesmonitor:sns:setup-complaints-topic');
        $this->addArgument('name', InputArgument::REQUIRED, 'Topic name to create, follows AWS naming rules');
    }

    /**
     * Executes the command.
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return null|int null or 0 if everything went fine, or an error code
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // Make the common configurations
        $this->configureCommand('aws_ses_monitor.complaints_endpoint');

        // Show to developer the selction of identities
        $selectedIdentities = $this->getHelper('question')->ask($input, $output, $this->createIdentitiesQuestion());

        // Create and persist the topic
        $topicArn = $this->createSnsTopic($input->getArgument('name'));

        $output->writeln("\nTopic created: " . $topicArn . "\n");

        // subscribe selected SES identities to SNS topic
        $output->writeln(sprintf('Registering <comment>"%s"</comment> topic for identities:', $input->getArgument('name')));
        foreach ($selectedIdentities as $identity) {
            $output->write($identity . ' ... ');
            $this->setIdentityInSesClient($identity, 'Complaint');
            $output->writeln('OK');
        }

        $subscribe = $this->buildSubscribeArray();
        $response = $this->getSnsClient()->subscribe($subscribe);

        $output->writeln(sprintf("\nSubscription endpoint URI: <comment>%s</comment>\n", $subscribe['Endpoint']));
        $output->writeln(sprintf("Subscription status: <comment>%s</comment>", $response->get('SubscriptionArn')));
    }
}