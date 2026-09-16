export default class BlogComments extends ContenaComponent {
    init() {
        this.forms = [...this.el.querySelectorAll('[data-blog-comment-form]')];
        this.replyToggles = [...this.el.querySelectorAll('[data-reply-toggle]')];

        this.submitHandler = this.submit.bind(this);
        this.toggleReplyHandler = this.toggleReply.bind(this);
        this.forms.forEach((form) => form.addEventListener('submit', this.submitHandler));
        this.replyToggles.forEach((toggle) => toggle.addEventListener('click', this.toggleReplyHandler));
    }

    toggleReply(event) {
        event.currentTarget.nextElementSibling?.classList.toggle('d-none');
    }

    async submit(event) {
        event.preventDefault();

        const commentUrl = this.el.dataset.commentUrl;
        if (!commentUrl) {
            return;
        }

        const form = event.currentTarget;
        const content = form.elements.content.value;
        const parentId = form.dataset.parentId;
        const body = parentId ? { content, parentId } : { content };
        const response = await fetch(commentUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body),
        });

        if (response.ok) {
            window.location.reload();
        }
    }

    destroy() {
        this.forms.forEach((form) => form.removeEventListener('submit', this.submitHandler));
        this.replyToggles.forEach((toggle) => toggle.removeEventListener('click', this.toggleReplyHandler));
    }
}
