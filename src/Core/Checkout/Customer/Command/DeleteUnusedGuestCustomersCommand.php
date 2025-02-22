<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Command;

use Shopware\Core\Checkout\Customer\DeleteUnusedGuestCustomerService;
use Shopware\Core\Framework\Adapter\Console\ShopwareStyle;
use Shopware\Core\Framework\Context;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @package customer-order
 */
#[AsCommand(
    name: 'customer:delete-unused-guests',
    description: 'Delete unused guest customers',
)]
class DeleteUnusedGuestCustomersCommand extends Command
{
    private DeleteUnusedGuestCustomerService $deleteUnusedGuestCustomerService;

    /**
     * @internal
     */
    public function __construct(DeleteUnusedGuestCustomerService $deleteUnusedGuestCustomerService)
    {
        parent::__construct();

        $this->deleteUnusedGuestCustomerService = $deleteUnusedGuestCustomerService;
    }

    protected function configure(): void
    {
        $this->setDescription('Delete unused guest customers without orders');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new ShopwareStyle($input, $output);

        $context = Context::createDefaultContext();

        $count = $this->deleteUnusedGuestCustomerService->countUnusedCustomers($context);

        if ($count === 0) {
            $io->comment('No unused guest customers found.');

            return self::SUCCESS;
        }

        $confirm = $io->confirm(
            \sprintf('Are you sure that you want to delete %d guest customers?', $count),
            false
        );

        if (!$confirm) {
            $io->caution('Aborting due to user input.');

            return self::SUCCESS;
        }

        $progressBar = $io->createProgressBar($count);

        do {
            $ids = $this->deleteUnusedGuestCustomerService->deleteUnusedCustomers($context);
            $progressBar->advance(\count($ids));
        } while ($ids);

        $progressBar->finish();

        $io->success(\sprintf('Successfully deleted %d guest customers.', $count));

        return self::SUCCESS;
    }
}
