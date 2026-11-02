<?php

namespace App\Tests\Functional;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class AccessibilityTest extends WebTestCase
{
    public function testRepeatedPasswordsDescribeTheMismatchBeforeHelpOnBothFields(): void
    {
        static::bootKernel();
        $field = static::getContainer()->get('form.factory')->createNamed('password_check', \Symfony\Component\Form\Extension\Core\Type\RepeatedType::class, null, [
            'type' => \Symfony\Component\Form\Extension\Core\Type\PasswordType::class,
            'invalid_message' => 'Les mots de passe doivent être identiques.',
            'first_options' => ['help' => 'Au moins 12 caractères.'],
            'csrf_protection' => false,
        ]);
        $template = static::getContainer()->get('twig')->createTemplate('{{ form_widget(field) }}');
        $initial = new \Symfony\Component\DomCrawler\Crawler($template->render(['field' => $field->createView()]));
        self::assertCount(0, $initial->filter('[aria-invalid="true"]'));
        self::assertSame('password_check_first_help', $initial->filter('#password_check_first')->attr('aria-describedby'));
        self::assertFalse($initial->filter('#password_check_second')->getNode(0)->hasAttribute('aria-describedby'));
        $field->submit(['first' => 'Une phrase suffisamment longue', 'second' => 'Une autre phrase suffisamment longue']);
        $html = $template->render(['field' => $field->createView()]);
        $crawler = new \Symfony\Component\DomCrawler\Crawler($html);
        foreach (['first', 'second'] as $part) {
            $input = $crawler->filter('#password_check_'.$part);
            self::assertSame('true', $input->attr('aria-invalid'));
            self::assertSame('password_check_first_error1', explode(' ', $input->attr('aria-describedby'))[0]);
        }
        self::assertStringContainsString('password_check_first_help', $crawler->filter('#password_check_first')->attr('aria-describedby'));
        self::assertSame('Les mots de passe doivent être identiques.', $crawler->filter('#password_check_first_error1')->text());
        self::assertCount(1, $crawler->filter('#password_check_first_error1'));
    }

    public function testCompactProfileKeepsAnAccessibleNameWhenItsTextIsHidden(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $em = static::getContainer()->get(\Doctrine\ORM\EntityManagerInterface::class);
        self::assertSame('shortflow_test', $em->getConnection()->fetchOne('SELECT current_database()'));
        $em->getConnection()->beginTransaction();
        try {
            $owner = new \App\Entity\User('compact-profile-'.bin2hex(random_bytes(6)).'@example.test');
            $owner->verify();
            $owner->setPassword('unused');
            $owner->setDisplayName('Camille <création>');
            $em->persist($owner);
            $em->flush();
            $client->loginUser($owner);
            $client->request('GET', '/studio');
            self::assertResponseIsSuccessful();
            self::assertSelectorExists('.studio-profile[aria-label="Mon compte · Camille <création>"]');
        } finally {
            $em->getConnection()->rollBack();
            $em->clear();
        }
    }

    public function testDirectWidgetsKeepTheirHelpAndExposeReturnedErrors(): void
    {
        static::bootKernel();
        $field = static::getContainer()->get('form.factory')->createNamed('direct', \Symfony\Component\Form\Extension\Core\Type\TextType::class, null, [
            'constraints' => [new \Symfony\Component\Validator\Constraints\NotBlank()],
            'attr' => ['aria-describedby' => 'existing-help'],
        ]);
        $field->submit('');
        $html = static::getContainer()->get('twig')->createTemplate('{{ form_widget(field) }}{{ form_errors(field) }}')->render(['field' => $field->createView()]);
        $crawler = new \Symfony\Component\DomCrawler\Crawler($html);
        self::assertSame('true', $crawler->filter('input')->attr('aria-invalid'));
        self::assertSame('direct_error1 existing-help', $crawler->filter('input')->attr('aria-describedby'));
        self::assertCount(1, $crawler->filter('ul.form-errors[role="alert"] #direct_error1'));

        $model = static::getContainer()->get('form.factory')->createNamed('model', \Symfony\Component\Form\Extension\Core\Type\ChoiceType::class, null, ['choices' => ['Kling' => 'kling-25']]);
        $model->submit('unavailable');
        $html = static::getContainer()->get('twig')->render('ai/_model_picker.html.twig', [
            'modelForm' => $model->createView(), 'groups' => [], 'models' => [], 'modelChange' => 'change->creation#changeModel',
        ]);
        $crawler = new \Symfony\Component\DomCrawler\Crawler($html);
        foreach (['#model', '#creation-model-group', '#creation-resolution'] as $selector) {
            self::assertSame('true', $crawler->filter($selector)->attr('aria-invalid'));
            self::assertSame('model_error1', $crawler->filter($selector)->attr('aria-describedby'));
        }
        self::assertCount(1, $crawler->filter('#model_error1'));
    }

    public function testPublicSkipDestinationsAndReturnedErrorsRemainAccessible(): void
    {
        $client = static::createClient([], ['REMOTE_ADDR' => '192.0.2.'.random_int(50, 240)]);
        self::assertSame('shortflow_test', static::getContainer()->get(Connection::class)->fetchOne('SELECT current_database()'));
        foreach (['/', '/connexion', '/inscription', '/informations-legales'] as $path) {
            $client->request('GET', $path);
            self::assertResponseIsSuccessful();
            self::assertSelectorExists('a.skip[href="#contenu"]');
            self::assertSelectorExists('main#contenu[tabindex="-1"]');
            self::assertSelectorExists('body[data-controller~="accessibility"]');
        }
        $crawler = $client->request('GET', '/inscription');
        $client->submit($crawler->selectButton('Créer mon compte')->form([
            'registration[email]' => 'invalid',
            'registration[password][first]' => 'short',
            'registration[password][second]' => 'different',
        ]));
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorExists('ul.form-errors[role="alert"]');
        $field = $client->getCrawler()->filter('#registration_email');
        self::assertSame('true', $field->attr('aria-invalid'));
        $descriptions = explode(' ', $field->attr('aria-describedby'));
        self::assertContains('registration_email_error1', $descriptions);
        self::assertSelectorExists('#registration_email_error1');
    }
}
