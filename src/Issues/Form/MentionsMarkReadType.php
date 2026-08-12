<?php

declare(strict_types=1);

namespace App\Issues\Form;

use Nowo\FormKitBundle\Form\FormKitAbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Mark a single dashboard mention as read (CSRF + filter redirect hiddens).
 */
final class MentionsMarkReadType extends FormKitAbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var array<string, scalar> $query */
        $query = $options['redirect_query'];

        $this->withBuilder($builder, function () use ($query): void {
            foreach (['project', 'unread', 'per_page'] as $key) {
                $this->addNamedField($key, 'hidden', [
                    'required' => false,
                    'empty_data' => '',
                    'data' => (string) ($query[$key] ?? ''),
                ]);
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_protection' => true,
            'csrf_token_id' => 'mention_read',
            'redirect_query' => [],
        ]);
        $resolver->setAllowedTypes('redirect_query', 'array');
    }
}
