<?php declare(strict_types=1);
/**
 * @author Nicolas CARPi <nico-git@deltablot.email>
 * @copyright 2012, 2022 Nicolas CARPi
 * @see https://www.elabftw.net Official website
 * @license AGPL-3.0
 * @package elabftw
 */

namespace Elabftw\Elabftw;

use function dirname;
use Elabftw\Controllers\LoginController;
use Elabftw\Enums\Action;
use Elabftw\Enums\BasePermissions;
use Elabftw\Exceptions\DatabaseErrorException;
use Elabftw\Exceptions\FilesystemErrorException;
use Elabftw\Exceptions\IllegalActionException;
use Elabftw\Exceptions\ImproperActionException;
use Elabftw\Exceptions\InvalidCredentialsException;
use Elabftw\Services\Filter;
use Elabftw\Services\LocalAuth;
use Elabftw\Services\MfaHelper;
use Exception;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Deal with requests sent from the user control panel
 */
require_once dirname(__DIR__) . '/init.inc.php';
$tab = 1;
$Response = new RedirectResponse(sprintf('../../ucp.php?tab=%d', $tab));

$postData = $Request->request->all();
try {
    // TAB 1 : PREFERENCES
    if ($Request->request->has('lang')) {
        // the csrf is of course not a column that needs patching so remove it
        unset($postData['csrf']);
        $postData['default_read'] = (BasePermissions::tryFrom($Request->request->getInt('default_read')) ?? BasePermissions::MyTeams)->toJson();
        $postData['default_write'] = (BasePermissions::tryFrom($Request->request->getInt('default_write')) ?? BasePermissions::User)->toJson();
        $App->Users->patch(Action::Update, $postData);
    }
    // END TAB 1

    // TAB 2 : ACCOUNT
    if ($Request->request->has('use_mfa')) {
        $tab = 2;
        // if user is authenticated through external service we skip the password verification
        if ($App->Users->userData['auth_service'] === LoginController::AUTH_LOCAL) {
            // check that we got the good password
            $LocalAuth = new LocalAuth($App->Users->userData['email'], $Request->request->get('currpass'));
            try {
                $LocalAuth->tryAuth();
            } catch (InvalidCredentialsException $e) {
                throw new ImproperActionException('The current password is not valid!');
            }
            // update the email if necessary
            if (isset($params['email']) && ($params['email'] !== $App->Users->userData['email'])) {
                $App->Users->patch(Action::Update, array('email' => $params['email']));
            }
        }

        // CHANGE PASSWORD (only for local accounts)
        if (!empty($Request->request->get('newpass')) && (int) $App->Users->userData['auth_service'] === LoginController::AUTH_LOCAL) {
            $App->Users->patch(Action::Update, array('password' => $postData['newpass']));
        }

        // TWO FACTOR AUTHENTICATION
        $useMFA = Filter::onToBinary($postData['use_mfa'] ?? '');
        $MfaHelper = new MfaHelper($App->Users->userData['userid']);

        if ($useMFA && !$App->Users->userData['mfa_secret']) {
            // Need to request verification code to confirm user got secret and can authenticate in the future by MFA
            // so we will require mfa, redirect the user to login
            // which will pickup that enable_mfa is there so it will display the qr code to initialize the process
            // and after that we redirect on ucp back
            // the mfa_secret is not yet saved to the DB
            $App->Session->set('mfa_auth_required', true);
            $App->Session->set('mfa_secret', $MfaHelper->generateSecret());
            $App->Session->set('enable_mfa', true);

            // This will redirect user right away to verify mfa code
            $Response = new RedirectResponse('../../login.php');
            $Response->send();
            exit;

        // Disable MFA
        } elseif (!$useMFA && $App->Users->userData['mfa_secret']) {
            $MfaHelper->removeSecret();
        }
    }
    // END TAB 2

    $App->Session->getFlashBag()->add('ok', _('Saved'));
    $Response = new RedirectResponse(sprintf('../../ucp.php?tab=%d', $tab));
} catch (ImproperActionException $e) {
    // show message to user
    $App->Session->getFlashBag()->add('ko', $e->getMessage());
} catch (IllegalActionException $e) {
    $App->Log->notice('', array(array('userid' => $App->Session->get('userid')), array('IllegalAction', $e->getMessage())));
    $App->Session->getFlashBag()->add('ko', Tools::error(true));
} catch (DatabaseErrorException | FilesystemErrorException $e) {
    $App->Log->error('', array(array('userid' => $App->Session->get('userid')), array('Error', $e)));
} catch (Exception $e) {
    $App->Log->error('', array(array('userid' => $App->Session->get('userid')), array('Exception' => $e)));
    $App->Session->getFlashBag()->add('ko', Tools::error());
} finally {
    $Response->send();
}
