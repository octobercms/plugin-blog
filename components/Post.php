<?php namespace RainLab\Blog\Components;

use BackendAuth;
use Cms\Classes\Page;
use RainLab\Blog\Models\Post as BlogPost;
use RainLab\Blog\Classes\ComponentAbstract;

class Post extends ComponentAbstract
{
    /**
     * @var BlogPost The post model used for display.
     */
    public $post;

    /**
     * @var string Reference to the page name for linking to categories.
     */
    public $categoryPage;

    public function componentDetails()
    {
        return [
            'name'        => 'rainlab.blog::lang.settings.post_title',
            'description' => 'rainlab.blog::lang.settings.post_description'
        ];
    }

    public function defineProperties()
    {
        return [
            'slug' => [
                'title'       => 'rainlab.blog::lang.settings.post_slug',
                'description' => 'rainlab.blog::lang.settings.post_slug_description',
                'default'     => '{{ :slug }}',
                'type'        => 'string',
            ],
            'categoryPage' => [
                'title'       => 'rainlab.blog::lang.settings.post_category',
                'description' => 'rainlab.blog::lang.settings.post_category_description',
                'type'        => 'dropdown',
                'default'     => 'blog/category',
            ],
        ];
    }

    public function getCategoryPageOptions()
    {
        return Page::sortBy('baseFileName')->lists('baseFileName', 'baseFileName');
    }

    public function onRun()
    {
        $this->categoryPage = $this->page['categoryPage'] = $this->property('categoryPage');
        $this->post = $this->page['post'] = $this->loadPost();
    }

    public function onRender()
    {
        if (empty($this->post)) {
            $this->post = $this->page['post'] = $this->loadPost();
        }
    }

    protected function loadPost()
    {
        $slug = $this->property('slug');

        $post = new BlogPost;

        $post = $post->isClassExtendedWith('RainLab.Translate.Behaviors.TranslatableModel')
            ? $post->transWhere('slug', $slug)
            : $post->where('slug', $slug);

        if (!$this->checkEditor()) {
            $post = $post->isPublished();
        }

        $post = $post->first();

        /*
         * Add a "url" helper attribute for linking to each category
         */
        if ($post && $post->categories->count()) {
            $blogCategoriesComponent = $this->getComponent('blogCategories', $this->categoryPage);

            $post->categories->each(function($category) use ($blogCategoriesComponent) {
                $category->setUrl($this->categoryPage, $this->controller, [
                    'slug' => $this->urlProperty($blogCategoriesComponent, 'slug')
                ]);
            });
        }

        return $post;
    }

    public function previousPost()
    {
        return $this->getPostSibling(-1);
    }

    public function nextPost()
    {
        return $this->getPostSibling(1);
    }

    protected function getPostSibling($direction = 1)
    {
        if (!$this->post) {
            return;
        }

        $method = $direction === -1 ? 'previousPost' : 'nextPost';

        if (!$post = $this->post->$method()) {
            return;
        }

        $postPage = $this->getPage()->getBaseFileName();

        $blogPostComponent = $this->getComponent('blogPost', $postPage);
        $blogCategoriesComponent = $this->getComponent('blogCategories', $this->categoryPage);

        $post->setUrl($postPage, $this->controller, [
            'slug' => $this->urlProperty($blogPostComponent, 'slug')
        ]);

        $post->categories->each(function($category) use ($blogCategoriesComponent) {
            $category->setUrl($this->categoryPage, $this->controller, [
                'slug' => $this->urlProperty($blogCategoriesComponent, 'slug')
            ]);
        });

        return $post;
    }

    protected function checkEditor()
    {
        $backendUser = BackendAuth::getUser();

        return $backendUser && $backendUser->hasAccess('rainlab.blog.access_posts');
    }
}
