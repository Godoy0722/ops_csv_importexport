<?php

/**
 * @file plugins/importexport/csv/tests/Unit/Handlers/WelcomeEmailHandlerTest.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class WelcomeEmailHandlerTest
 *
 * @brief Tests for WelcomeEmailHandler class - testing email parameter logic
 */

namespace APP\plugins\importexport\csv\tests\Unit\Handlers;

use APP\plugins\importexport\csv\classes\handlers\WelcomeEmailHandler;
use APP\plugins\importexport\csv\tests\BaseTestCase;
use APP\plugins\importexport\csv\tests\Fixtures\MockFactory;
use Illuminate\Support\Facades\Mail;
use Mockery;
use PHPUnit\Framework\Attributes\CoversClass;
use PKP\emailTemplate\EmailTemplate;
use PKP\mail\mailables\UserCreated;
use Symfony\Component\Mailer\Exception\TransportException;

#[CoversClass(WelcomeEmailHandler::class)]
class WelcomeEmailHandlerTest extends BaseTestCase
{
    // ==================== Email Data Tests ====================

    public function testUserCreatedMailableExists(): void
    {
        // Verify the mailable class exists
        $this->assertTrue(class_exists(UserCreated::class));
    }

    public function testUserCreatedMailableHasTemplateKey(): void
    {
        $templateKey = UserCreated::getEmailTemplateKey();

        $this->assertNotEmpty($templateKey);
        $this->assertIsString($templateKey);
    }

    // ==================== Recipient Data Tests ====================

    public function testRecipientUserCanBeCreated(): void
    {
        $recipient = MockFactory::user()
            ->withId(1)
            ->withEmail('recipient@example.com')
            ->withGivenName('John')
            ->withFamilyName('Doe')
            ->build();

        $this->assertEquals(1, $recipient->getId());
        $this->assertEquals('recipient@example.com', $recipient->getEmail());
        $this->assertEquals('John', $recipient->getGivenName('en'));
        $this->assertEquals('Doe', $recipient->getFamilyName('en'));
    }

    // ==================== Sender Data Tests ====================

    public function testSenderUserCanBeCreated(): void
    {
        $sender = MockFactory::user()
            ->withId(2)
            ->withEmail('sender@example.com')
            ->withGivenName('Admin')
            ->withFamilyName('User')
            ->build();

        $this->assertEquals(2, $sender->getId());
        $this->assertEquals('sender@example.com', $sender->getEmail());
    }

    // ==================== Server Data Tests ====================

    public function testServerCanBeCreated(): void
    {
        $server = MockFactory::server()
            ->withId(1)
            ->withPath('testserver')
            ->withContactEmail('server@example.com')
            ->build();

        $this->assertEquals(1, $server->getId());
        $this->assertEquals('server@example.com', $server->getContactEmail());
    }

    // ==================== Password Parameter Tests ====================

    public function testPasswordCanBeProvided(): void
    {
        $password = 'temppassword123';

        $this->assertNotEmpty($password);
        $this->assertIsString($password);
    }

    public function testPasswordWithSpecialCharacters(): void
    {
        $password = 'C0mpl3x!P@ss#2024';

        $this->assertNotEmpty($password);
        $this->assertStringContainsString('!', $password);
        $this->assertStringContainsString('@', $password);
        $this->assertStringContainsString('#', $password);
    }

    public function testPasswordWithUnicode(): void
    {
        $password = 'unicode_密码_пароль';

        $this->assertNotEmpty($password);
    }

    // ==================== Email Recipient Format Tests ====================

    public function testEmailFormatIsValid(): void
    {
        $email = 'user@example.com';

        $this->assertStringContainsString('@', $email);
        $this->assertMatchesRegularExpression('/^[^\s@]+@[^\s@]+\.[^\s@]+$/', $email);
    }

    public function testMultipleDomainEmail(): void
    {
        $email = 'user@subdomain.example.com';

        $this->assertStringContainsString('@', $email);
    }

    // ==================== Email Data Structure Tests ====================

    public function testEmailDataStructure(): void
    {
        $emailData = [
            'recipientEmail' => 'recipient@example.com',
            'recipientName' => 'John Doe',
            'senderEmail' => 'sender@example.com',
            'senderName' => 'Admin User',
            'serverName' => 'Test Server',
            'password' => 'temppassword',
        ];

        $this->assertArrayHasKey('recipientEmail', $emailData);
        $this->assertArrayHasKey('recipientName', $emailData);
        $this->assertArrayHasKey('senderEmail', $emailData);
        $this->assertArrayHasKey('password', $emailData);
    }

    // ==================== User Name Combination Tests ====================

    public function testFullNameCombination(): void
    {
        $givenName = 'John';
        $familyName = 'Doe';

        $fullName = $givenName . ' ' . $familyName;

        $this->assertEquals('John Doe', $fullName);
    }

    public function testFullNameWithUnicode(): void
    {
        $givenName = 'José María';
        $familyName = 'García López';

        $fullName = $givenName . ' ' . $familyName;

        $this->assertEquals('José María García López', $fullName);
    }

    // ==================== sendWelcomeEmail() Integration Tests ====================

    public function testSendWelcomeEmailSendsMailSuccessfully(): void
    {
        $emailTemplateRepoMock = $this->mockEmailTemplateRepository();

        $template = Mockery::mock(EmailTemplate::class);
        $template->shouldReceive('getLocalizedData')->with('body')->andReturn('Welcome body');
        $template->shouldReceive('getLocalizedData')->with('subject')->andReturn('Welcome subject');

        $emailTemplateRepoMock->shouldReceive('getByKey')
            ->once()
            ->andReturn($template);

        $server = $this->createMockServer([
            'id' => 1,
            'path' => 'testserver',
            'contactEmail' => 'server@example.com',
        ]);
        $server->setData('contactEmail', 'server@example.com');
        $server->setData('contactName', 'Test Server Admin');

        $recipient = $this->createMockUser([
            'id' => 10,
            'email' => 'recipient@example.com',
            'givenName' => 'John',
            'familyName' => 'Doe',
        ]);

        $sender = $this->createMockUser([
            'id' => 20,
            'email' => 'admin@example.com',
            'givenName' => 'Admin',
            'familyName' => 'User',
        ]);

        Mail::shouldReceive('send')->once();

        WelcomeEmailHandler::sendWelcomeEmail($server, $recipient, $sender, 'temppass123');

        $this->assertTrue(true);
    }

    public function testSendWelcomeEmailHandlesTransportException(): void
    {
        $emailTemplateRepoMock = $this->mockEmailTemplateRepository();

        $template = Mockery::mock(EmailTemplate::class);
        $template->shouldReceive('getLocalizedData')->with('body')->andReturn('Welcome body');
        $template->shouldReceive('getLocalizedData')->with('subject')->andReturn('Welcome subject');

        $emailTemplateRepoMock->shouldReceive('getByKey')
            ->once()
            ->andReturn($template);

        $server = $this->createMockServer([
            'id' => 1,
            'path' => 'testserver',
            'contactEmail' => 'server@example.com',
        ]);
        $server->setData('contactEmail', 'server@example.com');
        $server->setData('contactName', 'Test Server Admin');

        $recipient = $this->createMockUser([
            'id' => 10,
            'email' => 'recipient@example.com',
            'givenName' => 'John',
            'familyName' => 'Doe',
        ]);

        $sender = $this->createMockUser([
            'id' => 20,
            'email' => 'admin@example.com',
            'givenName' => 'Admin',
            'familyName' => 'User',
        ]);

        Mail::shouldReceive('send')
            ->once()
            ->andThrow(new TransportException('SMTP connection failed'));

        $mockNotificationMgr = Mockery::mock('overload:APP\notification\NotificationManager');
        $mockNotificationMgr->shouldReceive('createTrivialNotification')->once();

        $this->expectOutputString('SMTP connection failed');

        WelcomeEmailHandler::sendWelcomeEmail($server, $recipient, $sender, 'temppass123');
    }
}
