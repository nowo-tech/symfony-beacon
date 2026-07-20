<?php

declare(strict_types=1);

namespace App\Issues\Form;

use App\Identity\Entity\User;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\UX\Autocomplete\Form\AsEntityAutocompleteField;
use Symfony\UX\Autocomplete\Form\BaseEntityAutocompleteType;

/**
 * Autocomplete for project members (used as issue assignee).
 *
 * Pass signed `extra_options.project_id` so results stay scoped to that project.
 *
 * @extends AbstractType<User>
 */
#[AsEntityAutocompleteField(alias: 'project_member')]
final class ProjectMemberAutocompleteField extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'class' => User::class,
            'placeholder' => 'issues.assignee_unassigned',
            'translation_domain' => 'messages',
            'required' => false,
            'label' => false,
            'choice_label' => static function (User $user): string {
                $name = trim($user->getDisplayName());

                return '' !== $name ? $name.' ('.$user->getEmail().')' : $user->getEmail();
            },
            'security' => 'ROLE_USER',
            'max_results' => 20,
            'preload' => 'focus',
            'tom_select_options' => [
                'plugins' => ['clear_button'],
                'maxOptions' => 20,
                'dropdownParent' => 'body',
                'openOnFocus' => true,
                'highlight' => true,
            ],
            'filter_query' => static function (Options $options): \Closure {
                $extra = $options['extra_options'];
                $projectId = \is_array($extra) && isset($extra['project_id']) && is_numeric($extra['project_id'])
                    ? (int) $extra['project_id']
                    : 0;
                $maxResultsOption = $options['max_results'];
                $maxResults = \is_int($maxResultsOption) ? $maxResultsOption : 20;

                return static function (QueryBuilder $qb, string $query, EntityRepository $repository) use ($projectId, $maxResults): void {
                    $qb->innerJoin('entity.memberships', 'membership')
                        ->andWhere('membership.project = :projectId')
                        ->setParameter('projectId', $projectId)
                        ->orderBy('entity.displayName', 'ASC')
                        ->addOrderBy('entity.email', 'ASC')
                        ->setMaxResults($maxResults);

                    $term = trim($query);
                    if ('' === $term) {
                        return;
                    }

                    $qb->andWhere('entity.email LIKE :term OR entity.displayName LIKE :term')
                        ->setParameter('term', '%'.$term.'%');
                };
            },
        ]);
    }

    public function getParent(): string
    {
        return BaseEntityAutocompleteType::class;
    }
}
