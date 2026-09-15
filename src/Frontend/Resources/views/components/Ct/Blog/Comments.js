export default class BlogComments extends ContenaComponent {
    init() {
        this.form = this.el.querySelector('[data-blog-comment-form]');

        if (this.form) {
            this.submitHandler = this.submit.bind(this);
            this.form.addEventListener('submit', this.submitHandler);
        }
    }

    async submit(event) {
        event.preventDefault();

        const blogId = this.el.closest('[data-blog-id]')?.dataset.blogId;
        if (!blogId) {
            return;
        }

        const content = this.form.elements.content.value;
        const response = await fetch(`/channel-api/blog/${blogId}/comment`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ content }),
        });

        if (response.ok) {
            window.location.reload();
        }
    }

    destroy() {
        this.form?.removeEventListener('submit', this.submitHandler);
    }
}
