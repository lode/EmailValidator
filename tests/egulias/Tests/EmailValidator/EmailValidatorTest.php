<?php

namespace Egulias\Tests\EmailValidator;

use Egulias\EmailValidator\EmailValidator;
use Egulias\EmailValidator\Exception\AtextAfterCFWS;
use Egulias\EmailValidator\Exception\ConsecutiveAt;
use Egulias\EmailValidator\Exception\ConsecutiveDot;
use Egulias\EmailValidator\Exception\CRNoLF;
use Egulias\EmailValidator\Exception\DomainHyphened;
use Egulias\EmailValidator\Exception\DotAtEnd;
use Egulias\EmailValidator\Exception\DotAtStart;
use Egulias\EmailValidator\Exception\ExpectingATEXT;
use Egulias\EmailValidator\Exception\ExpectingDTEXT;
use Egulias\EmailValidator\Exception\NoDomainPart;
use Egulias\EmailValidator\Exception\NoLocalPart;
use Egulias\EmailValidator\Exception\UnclosedComment;
use Egulias\EmailValidator\Exception\UnclosedQuotedString;
use Egulias\EmailValidator\Exception\UnopenedComment;
use Egulias\EmailValidator\Warning\AddressLiteral;
use Egulias\EmailValidator\Warning\CFWSNearAt;
use Egulias\EmailValidator\Warning\CFWSWithFWS;
use Egulias\EmailValidator\Warning\Comment;
use Egulias\EmailValidator\Warning\IPV6Deprecated;
use Egulias\EmailValidator\Warning\IPV6DoubleColon;
use Egulias\EmailValidator\Warning\IPV6MaxGroups;
use Egulias\EmailValidator\Warning\NoDNSRecord;
use Egulias\EmailValidator\Warning\QuotedString;

class EmailValidatorTest extends \PHPUnit_Framework_TestCase
{
    protected $validator;

    protected function setUp()
    {
        $this->validator = new EmailValidator();
    }

    protected function tearDown()
    {
        $this->validator = null;
    }

    /**
     * @dataProvider getValidEmails
     */
    public function testValidEmails($email)
    {
        $this->assertTrue($this->validator->isValid($email));
    }

    public function testInvalidUTF8Email()
    {
        $validator = new EmailValidator;
        $email     = "\x80\x81\x82@\x83\x84\x85.\x86\x87\x88";

        $this->assertFalse($validator->isValid($email));
    }

    public function getValidEmails()
    {
        return array(
            array('â@iana.org'),
            array('fabien@symfony.com'),
            array('example@example.co.uk'),
            array('fabien_potencier@example.fr'),
            array('example@localhost'),
            array('fab\'ien@symfony.com'),
            array('fab\ ien@symfony.com'),
            array('example((example))@fakedfake.co.uk'),
            array('example@faked(fake).co.uk'),
            array('fabien+@symfony.com'),
            array('инфо@письмо.рф'),
            array('"username"@example.com'),
            array('"user,name"@example.com'),
            array('"user name"@example.com'),
            array('"user@name"@example.com'),
            array('"\a"@iana.org'),
            array('"test\ test"@iana.org'),
            array('""@iana.org'),
            array('"\""@iana.org'),
            array('müller@möller.de'),
            array('test@email*'),
            array('test@email!'),
            array('test@email&'),
            array('test@email^'),
            array('test@email%'),
            array('test@email$'),
        );
    }

    /**
     * @dataProvider getInvalidEmails
     */
    public function testInvalidEmails($email)
    {
        $this->assertFalse($this->validator->isValid($email));
    }

    public function getInvalidEmails()
    {
        return array(
            array('test@example.com test'),
            array('user  name@example.com'),
            array('user   name@example.com'),
            array('example.@example.co.uk'),
            array('example@example@example.co.uk'),
            array('(test_exampel@example.fr)'),
            array('example(example)example@example.co.uk'),
            array('.example@localhost'),
            array('ex\ample@localhost'),
            array('example@local\host'),
            array('example@localhost.'),
            array('user name@example.com'),
            array('username@ example . com'),
            array('example@(fake).com'),
            array('example@(fake.com'),
            array('username@example,com'),
            array('usern,ame@example.com'),
            array('user[na]me@example.com'),
            array('"""@iana.org'),
            array('"\"@iana.org'),
            array('"test"test@iana.org'),
            array('"test""test"@iana.org'),
            array('"test"."test"@iana.org'),
            array('"test".test@iana.org'),
            array('"test"' . chr(0) . '@iana.org'),
            array('"test\"@iana.org'),
            array(chr(226) . '@iana.org'),
            array('test@' . chr(226) . '.org'),
            array('\r\ntest@iana.org'),
            array('\r\n test@iana.org'),
            array('\r\n \r\ntest@iana.org'),
            array('\r\n \r\ntest@iana.org'),
            array('\r\n \r\n test@iana.org'),
            array('test@iana.org \r\n'),
            array('test@iana.org \r\n '),
            array('test@iana.org \r\n \r\n'),
            array('test@iana.org \r\n\r\n'),
            array('test@iana.org  \r\n\r\n '),
            array('test@iana/icann.org'),
            array('test@foo;bar.com'),
            array('test;123@foobar.com'),
            array('test@example..com'),
            array('email.email@email."'),
            array('test@email>'),
            array('test@email<'),
            array('test@email{'),
        );
    }

    /**
     * @dataProvider getInvalidEmailsWithErrors
     */
    public function testInvalidEmailsWithErrorsCheck($errors, $email)
    {
        $this->assertFalse($this->validator->isValid($email));

        $this->assertEquals($errors, $this->validator->getError());
    }

    public function getInvalidEmailsWithErrors()
    {
        return array(
            array(NoLocalPart::CODE, '@example.co.uk'),
            array(NoDomainPart::CODE, 'example@'),
            array(DomainHyphened::CODE, 'example@example-.co.uk'),
            array(DomainHyphened::CODE, 'example@example-'),
            array(ConsecutiveAt::CODE, 'example@@example.co.uk'),
            array(ConsecutiveDot::CODE, 'example..example@example.co.uk'),
            array(ConsecutiveDot::CODE, 'example@example..co.uk'),
            array(ExpectingATEXT::CODE, '<fabien_potencier>@example.fr'),
            array(DotAtStart::CODE, '.example@localhost'),
            array(DotAtStart::CODE, 'example@.localhost'),
            array(DotAtEnd::CODE, 'example@localhost.'),
            array(DotAtEnd::CODE, 'example.@example.co.uk'),
            array(UnclosedComment::CODE, '(example@localhost'),
            array(UnclosedQuotedString::CODE, '"example@localhost'),
            array(ExpectingATEXT::CODE, 'exa"mple@localhost'),
            array(UnclosedComment::CODE, '(example@localhost'),
            array(UnopenedComment::CODE, 'comment)example@localhost'),
            array(UnopenedComment::CODE, 'example(comment))@localhost'),
            array(UnopenedComment::CODE, 'example@comment)localhost'),
            array(UnopenedComment::CODE, 'example@localhost(comment))'),
            array(UnopenedComment::CODE, 'example@(comment))example.com'),
            //This was the original. But atext is not allowed after \n
            //array(EmailValidator::ERR_EXPECTING_ATEXT, "exampl\ne@example.co.uk"),
            array(AtextAfterCFWS::CODE, "exampl\ne@example.co.uk"),
            array(ExpectingDTEXT::CODE, "example@[[]"),
            array(AtextAfterCFWS::CODE, "exampl\te@example.co.uk"),
            array(CRNoLF::CODE, "example@exa\rmple.co.uk"),
            array(CRNoLF::CODE, "example@[\r]"),
            array(CRNoLF::CODE, "exam\rple@example.co.uk"),
        );
    }

    /**
     * @dataProvider getInvalidEmailsWithWarnings
     */
    public function testInvalidEmailsWithWarningsCheck($expectedWarnings, $email)
    {
        $this->assertTrue($this->validator->isValid($email, true));
        $warnings = $this->validator->getWarnings();
        $this->assertTrue(count($warnings) === count($expectedWarnings));

        foreach ($warnings as $warning) {
            $this->assertTrue(isset($expectedWarnings[$warning->code()]));
        }
    }

    /**
     * @dataProvider getInvalidEmailsWithWarnings
     */
    public function testInvalidEmailsWithDnsCheckAndStrictMode($expectedWarnings, $email)
    {
        $this->assertFalse($this->validator->isValid($email, true, true));

        $warnings = $this->validator->getWarnings();
        $this->assertTrue(count($warnings) === count($expectedWarnings));

        foreach ($warnings as $warning) {
            $this->assertTrue(isset($expectedWarnings[$warning->code()]));
        }
    }

    public function getInvalidEmailsWithWarnings()
    {
        return array(
            [
                [CFWSNearAt::CODE, NoDNSRecord::CODE],
                'example @example.co.uk'
            ],
            [
                [CFWSNearAt::CODE, NoDNSRecord::CODE],
                'example@ example.co.uk'
            ],
            [
                [Comment::CODE, NoDNSRecord::CODE],
                'example@example(examplecomment).co.uk'
            ],
            [
                [Comment::CODE, CFWSNearAt::CODE, NoDNSRecord::CODE],
                'example(examplecomment)@example.co.uk'
            ],
            [
                [QuotedString::CODE, CFWSWithFWS::CODE, NoDNSRecord::CODE],
                "\"\t\"@example.co.uk"
            ],
            [
                [QuotedString::CODE, CFWSWithFWS::CODE, NoDNSRecord::CODE],
                "\"\r\"@example.co.uk"
            ],
            [
                [AddressLiteral::CODE, NoDNSRecord::CODE],
                'example@[127.0.0.1]'
            ],
            [
                [AddressLiteral::CODE, NoDNSRecord::CODE],
                'example@[IPv6:2001:0db8:85a3:0000:0000:8a2e:0370:7334]'
            ],
            [
                [AddressLiteral::CODE, IPV6Deprecated::CODE, NoDNSRecord::CODE],
                'example@[IPv6:2001:0db8:85a3:0000:0000:8a2e:0370::]'
            ],
            [
                [AddressLiteral::CODE, IPV6MaxGroups::CODE, NoDNSRecord::CODE],
                'example@[IPv6:2001:0db8:85a3:0000:0000:8a2e:0370:7334::]'
            ],
            [

                [AddressLiteral::CODE, IPV6DoubleColon::CODE, NoDNSRecord::CODE],
                'example@[IPv6:1::1::1]'
            ],
//            array(
//                array(
//                    EmailValidator::RFC5322_DOMLIT_OBSDTEXT,
//                    EmailValidator::RFC5322_DOMAINLITERAL,
//                    EmailValidator::DNSWARN_NO_RECORD,
//                ),
//                "example@[\n]"
//            ),
//            array(
//                array(
//                    EmailValidator::RFC5322_DOMAINLITERAL,
//                    EmailValidator::DNSWARN_NO_RECORD,
//                ),
//                'example@[::1]'
//            ),
//            array(
//                array(
//                    EmailValidator::RFC5322_DOMAINLITERAL,
//                    EmailValidator::DNSWARN_NO_RECORD,
//                ),
//                'example@[::123.45.67.178]'
//            ),
//            array(
//                array(
//                    EmailValidator::RFC5322_IPV6_COLONSTRT,
//                    EmailValidator::RFC5321_ADDRESSLITERAL,
//                    EmailValidator::RFC5322_IPV6_GRPCOUNT,
//                    EmailValidator::DNSWARN_NO_RECORD,
//                ),
//                'example@[IPv6::2001:0db8:85a3:0000:0000:8a2e:0370:7334]'
//            ),
//            array(
//                array(
//                    EmailValidator::RFC5321_ADDRESSLITERAL,
//                    EmailValidator::RFC5322_IPV6_BADCHAR,
//                    EmailValidator::DNSWARN_NO_RECORD,
//                ),
//                'example@[IPv6:z001:0db8:85a3:0000:0000:8a2e:0370:7334]'
//            ),
//            array(
//                array(
//                    EmailValidator::RFC5321_ADDRESSLITERAL,
//                    EmailValidator::RFC5322_IPV6_COLONEND,
//                    EmailValidator::DNSWARN_NO_RECORD,
//                ),
//                'example@[IPv6:2001:0db8:85a3:0000:0000:8a2e:0370:]'
//            ),
//            array(
//                array(
//                    EmailValidator::RFC5321_QUOTEDSTRING,
//                    EmailValidator::DNSWARN_NO_RECORD
//                ),
//                '"example"@example.co.uk'
//            ),
//            array(
//                array(
//                    EmailValidator::RFC5322_LOCAL_TOOLONG,
//                    EmailValidator::DNSWARN_NO_RECORD
//                ),
//                'too_long_localpart_too_long_localpart_too_long_localpart_too_long_localpart@example.co.uk'
//            ),
//            array(
//                array(
//                    EmailValidator::RFC5322_LABEL_TOOLONG,
//                    EmailValidator::DNSWARN_NO_RECORD,
//                ),
//                'example@toolonglocalparttoolonglocalparttoolonglocalparttoolonglocalpart.co.uk'
//            ),
//            array(
//                array(
//                    EmailValidator::RFC5322_DOMAIN_TOOLONG,
//                    EmailValidator::RFC5322_TOOLONG,
//                    EmailValidator::DNSWARN_NO_RECORD,
//                ),
//                'example@toolonglocalparttoolonglocalparttoolonglocalparttoolonglocalparttoolonglocalparttoolonglocal'.
//                'parttoolonglocalparttoolonglocalparttoolonglocalparttoolonglocalparttoolonglocalparttoolonglocalpart'.
//                'toolonglocalparttoolonglocalparttoolonglocalparttoolonglocalpart'
//            ),
//            array(
//                array(
//                    EmailValidator::RFC5322_DOMAIN_TOOLONG,
//                    EmailValidator::RFC5322_TOOLONG,
//                    EmailValidator::DNSWARN_NO_RECORD,
//                ),
//                'example@toolonglocalparttoolonglocalparttoolonglocalparttoolonglocalparttoolonglocalparttoolonglocal'.
//                'parttoolonglocalparttoolonglocalparttoolonglocalparttoolonglocalparttoolonglocalparttoolonglocalpart'.
//                'toolonglocalparttoolonglocalparttoolonglocalparttoolonglocalpar'
//            ),
//            array(
//                array(
//                    EmailValidator::DNSWARN_NO_RECORD,
//                ),
//                'test@test'
//            ),
        );
    }

    public function testInvalidEmailsWithStrict()
    {
        $this->assertFalse($this->validator->isValid('"test"@test', false, true));
    }
}
