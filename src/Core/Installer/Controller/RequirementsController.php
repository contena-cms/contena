<?php declare(strict_types=1);

namespace Contena\Core\Installer\Controller;

use Contena\Core\Installer\Requirements\RequirementsValidatorInterface;
use Contena\Core\Installer\Requirements\Struct\RequirementsCheckCollection;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 */
class RequirementsController extends InstallerController
{
    /**
     * @param iterable|RequirementsValidatorInterface[] $validators
     */
    public function __construct(private readonly iterable $validators)
    {
    }

    #[Route(path: '/installer/requirements', name: 'installer.requirements', methods: ['GET', 'POST'])]
    public function requirements(Request $request): Response
    {
        $checks = new RequirementsCheckCollection();

        foreach ($this->validators as $validator) {
            $checks = $validator->validateRequirements($checks);
        }

        if ($request->isMethod(Request::METHOD_POST) && !$checks->hasError()) {
            return $this->redirectToRoute('installer.license');
        }

        return $this->renderInstaller(
            '@Installer/installer/requirements.html.twig',
            [
                'requirementChecks' => $checks,
                'noWayBack' => $request->getSession()->has('extendSteps'),
            ]
        );
    }
}
