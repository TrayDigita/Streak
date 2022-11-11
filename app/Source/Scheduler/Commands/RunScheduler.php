<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Source\Scheduler\Commands;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\SchemaException;
use Doctrine\DBAL\Schema\Table;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TrayDigita\Streak\Source\Console\Abstracts\RunCommand;
use TrayDigita\Streak\Source\Database\Instance;
use TrayDigita\Streak\Source\Helper\Util\Validator;
use TrayDigita\Streak\Source\Scheduler\Abstracts\AbstractTask;
use TrayDigita\Streak\Source\Models\ActionSchedulers;
use TrayDigita\Streak\Source\Models\ActionSchedulersLog;
use TrayDigita\Streak\Source\Scheduler\Scheduler;

class RunScheduler extends RunCommand
{
    protected string $command = 'scheduler';

    protected function configure()
    {
        parent::configure();
        $translate = sprintf(
            $this->translate('The %s to run the task scheduler'),
            '<info>%command.name%</info>'
        );
        $this
            ->setDescription(
                sprintf(
                    $this->translate(
                        '[%s] Run the scheduler'
                    ),
                    $this->command
                ),
            )
            ->setHelp(<<<EOT
$translate

    <info>%command.full_name%</info>
EOT
        );
        $database = $this->getContainer(Instance::class);
        // add action scheduler
        $database->registerModel(ActionSchedulers::class);
        $database->registerModel(ActionSchedulersLog::class);
    }

    /**
     * @throws SchemaException
     * @throws Exception
     */
    protected function doExecute(SymfonyStyle $symfonyStyle, InputInterface $input, OutputInterface $output): int
    {
        $database = $this->getContainer(Instance::class);
        $symfonyStyle->section(
            strtoupper($this->translate('Database Schema'))
        );
        $symfonyStyle->writeln(
            sprintf(
                '<fg=blue>%s</>',
                $this->translate('Checking database schema')
            )
        );
        $tables = [
            $database->createModel(ActionSchedulers::class)->getTableFromSchemaData(),
            $database->createModel(ActionSchedulersLog::class)->getTableFromSchemaData(),
        ];
        $sql_data = [];
        $platform = $database->getDatabasePlatform();
        $comparator = new Comparator($platform);
        /**
         * @var Table $table
         */
        foreach ($tables as $table) {
            if ($database->tablesExist($table->getName())) {
                $existingTable = $database->getTableDetails($table->getName());
                $diffTable = $comparator->compareTables($table, $existingTable);
                $sql_data[$table->getName()] = $platform->getAlterTableSQL($diffTable);
            }
        }
        if (!empty($sql_data)) {
            $symfonyStyle->writeln(
                sprintf(
                    '<fg=yellow>%s</>',
                    sprintf(
                        $this->translate('Creating database tables schema for %s'),
                        implode(', ', array_keys($sql_data))
                    )
                )
            );
            $database->multiQuery($sql_data, [], $exception);
            if ($exception) {
                throw $exception;
            }
        } else {
            $symfonyStyle->writeln(
                sprintf(
                    '<fg=green>%s</>',
                    $this->translate('Database schema tables for scheduler are complete.')
                )
            );
        }

        $scheduler = $this->getContainer(Scheduler::class);
        $filtered = array_keys(array_filter($scheduler->getPending(), fn($e) => $e->isNeedToRun()));
        $total = count($filtered);
        if ($total === 0) {
            $symfonyStyle->writeln(
                sprintf(
                    '<fg=green>%s</>',
                    $this->translate(
                        'No schedule to be execute'
                    )
                )
            );
            return self::SUCCESS;
        }

        $this->eventAdd("Scheduler:beforeRun", function () use ($symfonyStyle, $total) {
            $symfonyStyle->section(
                strtoupper($this->translate('Scheduler'))
            );
            $symfonyStyle->writeln(
                sprintf(
                    $this->translatePlural(
                        'Execute %d scheduler on total.',
                        'Execute %d schedulers on total.',
                        $total
                    ),
                    $total
                )
            );
        });

        $this->eventAdd(
            "Scheduler:before:run",
            function (string $name) use ($symfonyStyle) {
                $pendingMessage = sprintf(
                    '<fg=yellow>[%s]</>',
                    strtoupper($this->translate('Pending'))
                );
                $symfonyStyle->newLine();
                $progressBar = $symfonyStyle->createProgressBar(2);
                $progressBar->setFormat('[%bar%] %status% - %message%');
                $progressBar->setMessage(
                    sprintf(
                        '<fg=blue>%s</>',
                        Validator::getClassBaseName($name)
                    )
                );
                $progressBar->setMessage($pendingMessage, 'status');
                $progressBar->advance();
                return $progressBar;
            }
        );
        $this->eventAdd(
            "Scheduler:after:run",
            function (
                AbstractTask $result,
                ProgressBar $progressBar,
                $name
            ) use (
                $symfonyStyle
            ) {
                $progressBar->advance();
                $result = $result->getStatus();
                $color  = $result ? ($result->isSuccess() ? 'green' : 'red') : 'red';
                $message = $result ? $result->getStatusString() : $this->translate('Unknown');
                $progressBar->setMessage(
                    sprintf(
                        '<fg=%s>%s</>',
                        $color,
                        Validator::getClassBaseName($name)
                    )
                );
                $progressBar->setMessage(
                    sprintf(
                        '<fg=%s>[%s]</>',
                        $color,
                        strtoupper($message)
                    ),
                    'status'
                );
                $progressBar->finish();
                return $progressBar;
            }
        );
        $scheduler->start();
        $symfonyStyle->writeln('');
        return self::SUCCESS;
    }
}
