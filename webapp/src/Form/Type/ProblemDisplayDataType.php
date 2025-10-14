<?php
namespace App\Form\Type;

use App\Entity\ProblemDisplayData;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

class ProblemDisplayDataType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('displayName', TextType::class, [
                'required' => false,
                'label' => 'Display Name',
            ])
            ->add('description', TextareaType::class, [
                'required' => false,
                'label' => 'Description (HTML allowed)',
                'attr' => ['class' => 'js-description-input', 'rows' => 8],
            ])
            ->add('imageUrl', TextType::class, [
                'required' => false,
                'label' => 'Banner Image URL',
                'attr' => ['placeholder' => 'https://example.com/image.png'],
            ])
            ->add('bannerFile', FileType::class, [
                'required' => false,
                'label' => 'Upload Banner Image',
                'mapped' => false,
                'constraints' => [
                    new File([
                        'maxSize' => '5M',
                        'mimeTypes' => [
                            'image/png', 'image/jpeg', 'image/gif', 'image/webp', 'image/svg+xml', 'image/avif'
                        ],
                        'mimeTypesMessage' => 'Please upload a valid image file.',
                    ]),
                ],
            ])
            // metadata moved to dedicated tables; field removed
            ->add('attachmentFile', FileType::class, [
                'required' => false,
                'label' => 'Add Attachment (any file type)',
                'mapped' => false,
                'attr' => ['class' => 'js-attachmentfile-input'],
            ])
            ->add('attachmentLink', TextType::class, [
                'required' => false,
                'label' => 'Attachment Link (URL)',
                'mapped' => false,
                'attr' => ['placeholder' => 'https://example.com/file.zip', 'class' => 'js-attachmentlink-input'],
            ]);

        // Visibility/scope of the problem attachment (stored on problem_attachment.type in DB)
        $builder->add('attachmentScope', ChoiceType::class, [
            'required' => false,
            'mapped' => false,
            'label' => 'Attachment visibility',
            'choices' => [
                'Public' => 'public',
                'Hidden' => 'hidden',
            ],
            'placeholder' => 'Select visibility',
        ]);

        // Content type for the specific attachment content (stored on problem_attachment_content.type)
        $builder->add('attachmentContentType', ChoiceType::class, [
            'required' => false,
            'mapped' => false,
            'label' => 'Attachment content type',
            'choices' => [
                'Auto-detect (file mime)' => '',
                'Link' => 'link',
                'Zip' => 'zip',
                'Video' => 'video',
                'Image' => 'image',
                'Other' => 'other',
            ],
            'placeholder' => false,
        ]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => ProblemDisplayData::class,
        ]);
    }
}
