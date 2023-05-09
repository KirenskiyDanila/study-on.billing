<?php

namespace App\Command;
use App\Repository\CourseRepository;
use App\Repository\TransactionRepository;
use App\Repository\UserRepository;
use App\Service\Twig;
use DateTime;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

#[AsCommand(name: 'payment:report')]
class ReportCommand extends Command
{
    private Twig $twig;
    private TransactionRepository $transactionRepository;
    private CourseRepository $courseRepository;
    private MailerInterface $mailer;

    public function __construct(
        Twig $twig,
        CourseRepository $courseRepository,
        TransactionRepository $transactionRepository,
        MailerInterface $mailer,
        string $name = null
    ) {
        $this->twig = $twig;
        $this->courseRepository = $courseRepository;
        $this->transactionRepository = $transactionRepository;
        $this->mailer = $mailer;
        parent::__construct($name);
    }

    /**
     * @throws SyntaxError
     * @throws RuntimeError
     * @throws LoaderError
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $courses = $this->courseRepository->findAll();
        $now = new DateTime('now');
        $monthAgo = $now->modify("-30 days");
        $totalAmount = 0;
        $totalCount = 0;
        $coursesBilling = array();
        foreach ($courses as $course) {
            $transactions = $this->transactionRepository->findTransactionsForReport($course);
            if (count($transactions) > 0) {
                $courseBilling = array();
                $courseBilling['priceTotal'] = 0;
                foreach ($transactions as $transaction) {
                    $courseBilling['priceTotal'] += $transaction->getAmount();
                }
                $courseBilling['count'] = count($transactions);
                $totalAmount += $courseBilling['priceTotal'];
                $totalCount += $courseBilling['count'];
                $courseBilling['type'] = $course->getType();
                $courseBilling['title'] = $course->getTitle();
                $coursesBilling[] = $courseBilling;
            }
        }
        $report = $this->twig->render(
            'report.html.twig',
            [
                'now' => $now,
                'monthAgo' => $monthAgo,
                'coursesBilling' => $coursesBilling,
                'totalAmount' => $totalAmount,
                'totalCount' => $totalCount
            ]
        );
        try {
            $email = (new Email())
                ->to(new Address($_ENV['ADMIN_EMAIL']))
                ->from(new Address('admin@study-on.ru'))
                ->subject('Отчет за месяц.')
                ->html($report);

            $this->mailer->send($email);

            $output->writeln('Отчет успешно отправлен менеджеру!');
        } catch (TransportExceptionInterface $e) {
            $output->writeln($e->getMessage());
            $output->writeln(
                'Ошибка при формировании и отправке отчета .'
            );

            return Command::FAILURE;
        }
        return Command::SUCCESS;
    }

}