<?php

namespace SerendipityHQ\Bundle\AwsSesMonitorBundle\Plugin;

use Doctrine\ORM\EntityManager;
use SerendipityHQ\Bundle\AwsSesMonitorBundle\Model\Bounce;
use SerendipityHQ\Bundle\AwsSesMonitorBundle\Repository\BounceRepositoryInterface;
use SerendipityHQ\Bundle\AwsSesMonitorBundle\Model\Complaint;
use SerendipityHQ\Bundle\AwsSesMonitorBundle\Repository\ComplaintRepositoryInterface;
use Swift_Events_SendEvent;

/**
 * The SwiftMailer plugin.
 */
class MonitorFilterPlugin implements \Swift_Events_SendListener
{
    /** @var array */
    private $blacklisted = [];

    /** @var bool $bouncesConfig */
    private $bouncesConfig;

    private $bounceRepo;

    /** @var int $complaintsConfig */
    private $complaintsConfig;

    private $complaintRepo;

    /**
     * @param EntityManager $manager
     * @param array         $bouncesConfig    The configuration of bounces
     * @param array         $complaintsConfig The configuration of complaints
     */
    public function __construct(EntityManager $manager, array $bouncesConfig, array $complaintsConfig)
    {
        $this->bouncesConfig = $bouncesConfig['filter'];
        $this->bounceRepo = $manager->getRepository('AwsSesMonitorBundle:Bounce');

        $this->complaintsConfig = $complaintsConfig['filter'];
        $this->complaintRepo = $manager->getRepository('AwsSesMonitorBundle:Complaint');
    }

    /**
     * Invoked immediately before the MailMessage is sent.
     *
     * @param Swift_Events_SendEvent $event
     */
    public function beforeSendPerformed(Swift_Events_SendEvent $event)
    {
        $message = $event->getMessage();

        $message->setTo($this->filterBlacklisted($message->getTo()));
        $message->setCc($this->filterBlacklisted($message->getCc()));
        $message->setBcc($this->filterBlacklisted($message->getBcc()));
    }

    /**
     * Invoked immediately after the MailMessage is sent.
     *
     * @param Swift_Events_SendEvent $evt
     */
    public function sendPerformed(Swift_Events_SendEvent $evt)
    {
        $evt->setFailedRecipients(array_keys($this->blacklisted));
    }

    /**
     * @param $recipients
     *
     * @return mixed
     */
    private function filterBlacklisted($recipients)
    {
        if (!is_array($recipients)) {
            return $recipients;
        }

        $emails = array_keys($recipients);

        foreach ($emails as $email) {
            if ($this->isBounced($email) || $this->isComplained($email)) {
                $this->blacklisted[$email] = $recipients[$email];
                unset($recipients[$email]);
            }
        }

        return $recipients;
    }

    /**
     * @param $email
     *
     * @return bool
     */
    private function isBounced($email)
    {
        // Check if bounces have to be filtered
        if (false === $this->bouncesConfig['enabled']) {
            // No bounces filtering
            return false;
        }

        // Check if sending is forced
        if (true === $this->bouncesConfig['force_send']) {
            // No bounces filtering
            return false;
        }

        $bounce = $this->bounceRepo->findOneByEmail($email);

        if (!$bounce instanceof Bounce) {
            return false;
        }

        if ($bounce->getBounceCount() >= $this->bouncesConfig['max_bounces']) {
            return true;
        }

        return false;
    }

    /**
     * @param $email
     *
     * @return bool
     */
    private function isComplained($email)
    {
        // Check if bounces have to be filtered
        if (false === $this->complaintsConfig['enabled']) {
            // No bounces filtering
            return false;
        }

        // Check if sending is forced
        if (true === $this->complaintsConfig['force_send']) {
            return false;
        }

        $complaint = $this->complaintRepo->findOneByEmail($email);

        if (!$complaint instanceof Complaint) {
            return false;
        }

        return false;
    }
}
