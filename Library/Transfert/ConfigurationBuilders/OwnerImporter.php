<?php

/*
 * This file is part of the Claroline Connect package.
 *
 * (c) Claroline Consortium <consortium@claroline.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Claroline\CoreBundle\Library\Transfert\ConfigurationBuilders;

use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Claroline\CoreBundle\Library\Transfert\Importer;
use Claroline\CoreBundle\Persistence\ObjectManager;
use Symfony\Component\Config\Definition\Processor;
use JMS\DiExtraBundle\Annotation as DI;

/**
 * @DI\Service("claroline.importer.owner_importer")
 * @DI\Tag("claroline.importer")
 */
class OwnerImporter extends Importer implements ConfigurationInterface
{
    private $om;

    /**
     * @DI\InjectParams({
     *     "om" = @DI\Inject("claroline.persistence.object_manager")
     * })
     */
    public function __construct(ObjectManager $om)
    {
        $this->om = $om;
    }

    public function  getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('owner');
        $this->addOwnerSection($rootNode);

        return $treeBuilder;
    }

    //@todo double check it's in the available user name (with ['members']['users']
    public function addOwnerSection($rootNode)
    {
        $usernames = array();

        foreach($this->om->getRepository('Claroline\CoreBundle\Entity\User')->findUsernames() as $username)
        {
            $usernames[] = $username['username'];
        }

        $emails = array();

        foreach($this->om->getRepository('Claroline\CoreBundle\Entity\User')->findEmails() as $mail)
        {
            $emails[] = $mail['mail'];
        }

        $codes = array();

        foreach($this->om->getRepository('Claroline\CoreBundle\Entity\User')->findCodes() as $code)
        {
            $codes[] = $code['code'];
        }

        $rootNode
            ->children()
                ->scalarNode('first_name')->example('John')->isRequired()->end()
                ->scalarNode('last_name')->example('Doe')->isRequired()->end()
                ->scalarNode('username')->example('Jdoe')->isRequired()
                    ->validate()
                        ->ifTrue(
                            function ($v) use ($usernames) {
                                return call_user_func_array(
                                    __CLASS__ . '::usernameAlreadyExistsInDatabase',
                                    array($v, $usernames)
                                );
                            }
                        )
                        ->thenInvalid("The username %s already exists")
                    ->end()
                ->end()
                ->scalarNode('password')->example('password')->isRequired()->end()
                ->scalarNode('mail')->example('jdoe')->isRequired()
                    ->validate()
                        ->ifTrue(
                            function ($v) use ($emails) {
                                return call_user_func_array(
                                    __CLASS__ . '::emailAlreadyExistsInDatabase',
                                    array($v, $emails)
                                );
                            }
                        )
                        ->thenInvalid("The email %s already exists")
                    ->end()
                        ->validate()
                        ->ifTrue(
                            function ($v) use ($emails) {
                                return call_user_func_array(
                                    __CLASS__ . '::emailIsValid',
                                    array($v, $emails)
                                );
                            }
                        )
                        ->thenInvalid("The email %s is invalid")
                    ->end()
                ->end()
                ->scalarNode('code')->example('user#223456789')->isRequired()
                    ->validate()
                        ->ifTrue(
                            function ($v) use ($codes) {
                                return call_user_func_array(
                                    __CLASS__ . '::codeAlreadyExistsInDatabase',
                                    array($v, $codes)
                                );
                            }
                        )
                        ->thenInvalid("The code %s already exists")
                    ->end()
                ->end()
                ->arrayNode('roles')
                    ->prototype('array')
                        ->children()
                            ->scalarNode('name')->isRequired()->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ->end();
    }

    /**
     * Validate the workspace properties.
     *
     * @param array $data
     */
    public function validate(array $data)
    {
        $processor = new Processor();
        $processor->processConfiguration($this, $data);
    }

    public function getName()
    {
        return 'owner';
    }

    public static function emailAlreadyExistsInDatabase($v, $mails)
    {
        return in_array($v, $mails);
    }

    public static function codeAlreadyExistsInDatabase($v, $code)
    {
        return in_array($v, $code);
    }

    public static function usernameAlreadyExistsInDatabase($v, $usernames)
    {
        return in_array($v, $usernames);
    }

    public static function emailIsValid($v)
    {
        return !filter_var($v, FILTER_VALIDATE_EMAIL);
    }

}