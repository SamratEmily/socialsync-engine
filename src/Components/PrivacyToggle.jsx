/**
 * PrivacyToggle
 *
 * Renders a segmented control to select the privacy level for a post.
 *
 * Props:
 *   platform  string   – 'facebook' | 'linkedin'  (determines available options)
 *   value     string   – current selected value
 *   onChange  fn(val)  – called when the user picks a new option
 *   disabled  bool     – disable the control while a post is in progress
 */
const FACEBOOK_OPTIONS = [
    { value: 'public',  label: 'Public' },
    { value: 'friends', label: 'Friends' },
    { value: 'only_me', label: 'Only Me' },
];

const LINKEDIN_OPTIONS = [
    { value: 'public',      label: 'Public' },
    { value: 'connections', label: 'Connections' },
];

const PrivacyToggle = ( { platform, value, onChange, disabled = false } ) => {
    const options = platform === 'linkedin' ? LINKEDIN_OPTIONS : FACEBOOK_OPTIONS;

    return (
        <div className="flex items-center gap-1 p-1 bg-gray-100 rounded-lg">
            { options.map( ( opt ) => (
                <button
                    key={ opt.value }
                    type="button"
                    disabled={ disabled }
                    onClick={ () => onChange( opt.value ) }
                    className={
                        `px-3 py-1 text-xs font-medium rounded-md transition-all focus:outline-none ` +
                        ( value === opt.value
                            ? 'bg-white text-indigo-700 shadow-sm'
                            : 'text-gray-500 hover:text-gray-700' ) +
                        ( disabled ? ' opacity-50 cursor-not-allowed' : '' )
                    }
                >
                    { opt.label }
                </button>
            ) ) }
        </div>
    );
};

export default PrivacyToggle;
