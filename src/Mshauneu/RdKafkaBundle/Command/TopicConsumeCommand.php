<?php

namespace Mshauneu\RdKafkaBundle\Command;

use Mshauneu\RdKafkaBundle\Topic\TopicCommunicator;
use Mshauneu\RdKafkaBundle\Topic\TopicConsumer;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;


/**
 * Consume Command
 * 
 * @author Mike Shauneu <mike.shauneu@gmail.com>
 */
class TopicConsumeCommand extends ContainerAwareCommand
{
	
	/**
	 * {@inheritDoc}
	 * @see \Symfony\Component\Console\Command\Command::configure()
	 */
	protected function configure()
    {
		$this
			->setName('kafka:consumer')
			->addOption('consumer', null, InputOption::VALUE_REQUIRED, 'Consumer')
			->addOption('handler', null, InputOption::VALUE_REQUIRED, 'MessageHandler')
			->addOption('partition', 'p', InputOption::VALUE_OPTIONAL, 'Partition', 0)
			->addOption('offset', 'o', InputOption::VALUE_OPTIONAL, 'Offset', 'beginning')
			->addOption('timeout', 't', InputOption::VALUE_OPTIONAL, 'Timeout in ms', 1000);
	}

	/**
	 * {@inheritDoc}
	 * @see \Symfony\Component\Console\Command\Command::execute()
	 */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
		$consumer = $input->getOption('consumer');

		$topicConsumer = $this->getContainer()->get('mshauneu_rd_kafka')->getConsumer($consumer);
		if (!$topicConsumer) {
			throw new \Exception(sprintf("TopicConsumer with name '%s' is not defined", $consumer));
		}

		$handler = $input->getOption('handler');
		$messageHandler = $this->getContainer()->get($handler);
		if (!$messageHandler) {
			throw new \Exception(sprintf("Message Handler with name '%s' is not defined", $handler));
		}

		$partition = $input->getOption('partition');
        if(!is_numeric($partition) || $partition < 0) {
            throw new \Exception("Partition needs to be a number in the range 0..2^32-1");
        }

        $offsetName = $input->getOption('offset');
        switch (strtolower($offsetName)) {
            case 'latest':
            case 'last':
                $offset = TopicConsumer::OFFSET_END;
                break;
            case 'beginning':
            case 'first':
            default:
                $offset = TopicConsumer::OFFSET_BEGINNING;
                break;
        }

		$timeout = $input->getOption('timeout');
        if (!is_numeric($timeout)) {
            throw new \Exception("Timeout needs to be a number in the range 0..2^32-1");
        }

		$topicConsumer->consumeStart($offset, $partition);

        pcntl_signal(SIGTERM, [&$topicConsumer, 'stop']);
        pcntl_signal(SIGINT, [&$topicConsumer, 'stop']);
        pcntl_signal(SIGHUP, [&$topicConsumer, 'restart']);

        while($topicConsumer->isConsuming()) {
            $topicConsumer->consume($messageHandler, $partition, $timeout);
            pcntl_signal_dispatch();
        }

	}

}

