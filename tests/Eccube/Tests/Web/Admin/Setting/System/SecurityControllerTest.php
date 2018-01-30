<?php
/*
 * This file is part of EC-CUBE
 *
 * Copyright(c) 2000-2015 LOCKON CO.,LTD. All Rights Reserved.
 *
 * http://www.lockon.co.jp/
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
 */


namespace Eccube\Tests\Web\Admin\Setting\Shop;

use Eccube\Tests\Web\Admin\AbstractAdminWebTestCase;
use Monolog\Handler\NullHandler;
use org\bovigo\vfs\vfsStream;
use Symfony\Bridge\Monolog\Logger;
use Symfony\Component\Yaml\Yaml;

/**
 * Class SecurityControllerTest
 * @package Eccube\Tests\Web\Admin\Setting\Shop
 */
class SecurityControllerTest extends AbstractAdminWebTestCase
{
    protected $configFile;
    protected $pathFile;

    protected $configFileReal;
    protected $pathFileReal;

    protected $ipTest = '192.168.1.100';

    /**
     * Setup before test
     */
    public function setUp()
    {
        $this->markTestIncomplete(get_class($this).' は未実装です');
        parent::setUp(); // TODO: Change the autogenerated stub

        $log = new Logger('admin');
        // ログを出力させない
        $log->pushHandler(new NullHandler());

        $config = $this->eccubeConfig;
        // virtual directory
        vfsStream::setup('rootDir');

        $rootDir = $this->container->getParameter('kernel.project_dir');
        $this->configFileReal = $rootDir.'/app/config/eccube/packages/eccube.yaml';
        $this->pathFileReal = $rootDir.'/app/config/eccube/services.yaml';

        if (!file_exists($this->configFileReal) || !file_exists($this->pathFileReal)) {
            $this->markTestSkipped('Skip if not have config file');
        }

        $structure = array(
            'app' => array(
                'config' => array(
                    'eccube' => array(
                        'packages' => array(
                            'eccube.yaml' => file_get_contents($this->configFileReal)
                        ),
                        'services.yaml' => file_get_contents($this->pathFileReal)
                    )
                ),
            ),
        );



        $config['root_dir'] = vfsStream::url('rootDir');

        // TODO: Can not overwrite new value config
        // $this->app->overwrite('config', $config);
        // $this->eccubeConfig['root_dir'] = $config['root_dir'];  // visualize like this


        // dump file
        $this->configFile = $rootDir.'/app/config/eccube/packages/eccube.yaml';
        $this->pathFile = $rootDir.'/app/config/eccube/services.yaml';

        vfsStream::create($structure);
    }

    /**
     * Routing test
     */
    public function testRouting()
    {
        $this->client->request('GET', $this->generateUrl('admin_setting_system_security'));
        $this->assertTrue($this->client->getResponse()->isSuccessful());
    }

    /**
     * Submit test
     */
    public function testSubmit()
    {
        $formData = $this->createFormData();

        $this->client->request(
            'POST',
            $this->generateUrl('admin_setting_system_security'),
            array(
                'admin_security' => $formData,
            )
        );

        $this->assertTrue($this->client->getResponse()->isRedirection());
        // Message
        $outPut = $this->container->get('session')->getFlashBag()->get('eccube.admin.success');
        $this->actual = array_shift($outPut);
        $this->expected = 'admin.system.security.route.dir.complete';
        $this->verify();

//        $config = require $this->configFile;
        $config = Yaml::parseFile($this->configFile);
        $admin_allow_hosts = $config['parameters']['eccube.constants']['admin_allow_hosts'];

        $this->assertTrue(in_array($formData['admin_allow_hosts'], $admin_allow_hosts));

        /*$path = require $this->pathFile;
        $this->expected = $formData['admin_route_dir'];
        $this->actual = $path['admin_route'];
        $this->verify();*/
    }

    /**
     * Submit when empty
     */
    public function testSubmitEmpty()
    {
        $formData = $this->createFormData();
        $formData['admin_allow_hosts'] = null;
        $formData['force_ssl'] = null;

        $pathFile = $this->container->getParameter('kernel.project_dir').'/app/config/eccube/services.yaml';
        $config = Yaml::parseFile($pathFile);

        $formData['admin_route_dir'] = $config['parameters']['admin_route'];


        $this->client->request(
            'POST',
            $this->generateUrl('admin_setting_system_security'),
            array(
                'admin_security' => $formData,
            )
        );

        $this->assertTrue($this->client->getResponse()->isSuccessful());

        $config = require $this->configFile;
        $this->assertNull($config['admin_allow_hosts']);
    }

    /**
     * Submit form
     * @return array
     */
    public function createFormData()
    {
        $formData = array(
            '_token' => 'dummy',
            'admin_route_dir' => 'admintest',
            'admin_allow_hosts' => $this->ipTest,
            'force_ssl' => 1,
        );

        return $formData;
    }
}
