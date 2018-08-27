<?php namespace Hampel\SlackMessage;

/**
 * @see https://github.com/illuminate/notifications
 * @see https://github.com/illuminate/notifications/blob/master/Messages/SlackAttachmentField.php
 * @see https://laravel.com/docs/5.6/notifications#slack-notifications
 */

class SlackAttachmentField
{
    /**
     * The title field of the attachment field.
     *
     * @var string
     */
    protected $title;

    /**
     * The content of the attachment field.
     *
     * @var string
     */
    protected $content;

    /**
     * Whether the content is short.
     *
     * @var bool
     */
    protected $short = true;

    /**
     * Set the title of the field.
     *
     * @param  string $title
     * @return $this
     */
    public function title($title)
    {
        $this->title = $title;

        return $this;
    }

    /**
     * Set the content of the field.
     *
     * @param  string $content
     * @return $this
     */
    public function content($content)
    {
        $this->content = $content;

        return $this;
    }

    /**
     * Indicates that the content should not be displayed side-by-side with other fields.
     *
     * @return $this
     */
    public function long()
    {
        $this->short = false;

        return $this;
    }

    /**
     * Get the array representation of the attachment field.
     *
     * @return array
     */
    public function toArray()
    {
        return [
            'title' => $this->title,
            'value' => $this->content,
            'short' => $this->short,
        ];
    }

	/**
	 * Create a new SlackAttachmentField from a Laravel SlackAttachmentField - mostly just for testing purposes
	 *
	 * @param \Illuminate\Notifications\Messages\SlackAttachmentField $laravelAttachmentField
	 *
	 * @return SlackAttachmentField
	 */
    public static function fromLaravel(\Illuminate\Notifications\Messages\SlackAttachmentField $laravelAttachmentField)
    {
    	$field = $laravelAttachmentField->toArray();

    	$attachment = new self;
    	$attachment->title($field['title'])->content($field['value']);
    	if (!$field['short'])
	    {
	    	$attachment->long();
	    }

	    return $attachment;
    }
}
