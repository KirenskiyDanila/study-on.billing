<?php

namespace App\Command;

use App\Repository\TransactionRepository;
use App\Repository\UserRepository;
use App\Service\Twig;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

#[AsCommand(name: 'payment:ending:notification')]
class NotificationCommand extends Command
{
    private Twig $twig;
    private TransactionRepository $transactionRepository;
    private UserRepository $userRepository;
    private TranslatorInterface $translator;

    private MailerInterface $mailer;

    public function __construct(
        Twig $twig,
        TransactionRepository $transactionRepository,
        UserRepository $userRepository,
        MailerInterface $mailer,
        TranslatorInterface $translator,
        string $name = null
    ) {
        $this->twig = $twig;
        $this->transactionRepository = $transactionRepository;
        $this->userRepository = $userRepository;
        $this->mailer = $mailer;
        parent::__construct($name);
        $this->translator = $translator;
    }

    /**
     * @throws SyntaxError
     * @throws RuntimeError
     * @throws LoaderError
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {

        $users = $this->userRepository->findAll();
        foreach ($users as $user) {
            $transactions = $this->transactionRepository->findExpiringTransactions($user);

            if (count($transactions) > 0) {
                $report = $this->twig->render(
                    'rent_expiring_message.html.twig',
                    [
                        'transactions' => $transactions,
                    ]
                );
                try {
                    $email = (new Email())
                        ->to(new Address($user->getEmail()))
                        ->from(new Address('admin@study-on.ru'))
                        ->subject('Кончается аренда курсов.')
                        ->html($report);

                    $this->mailer->send($email);

                    $output->writeln($this->translator->trans(
                        'command.notification.success',
                        [],
                        'messages'
                    ) . $user->getEmail() . '!');
                } catch (TransportExceptionInterface $e) {
                    $output->writeln($e->getMessage());
                    $output->writeln(
                        $this->translator->trans(
                            'command.notification.error',
                            [],
                            'messages'
                        ) . $user->getEmail() . '.'
                    );
                    return Command::FAILURE;
                }
            }
        }
        $output->writeln('Конец отправки отчетов.');
        return Command::SUCCESS;
    }
}
