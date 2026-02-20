import { useState, useEffect } from '@wordpress/element';
import axios from 'axios';

/* -------------------------------------------------------------------------- */
/* Shared sub-components                                                       */
/* -------------------------------------------------------------------------- */

const Label = ( { htmlFor, children, required = false } ) => (
    <label htmlFor={ htmlFor } className="block text-sm font-medium text-gray-700 mb-1">
        { children }
        { required && <span className="text-red-500 ml-0.5">*</span> }
    </label>
);

const Input = ( { id, type = 'text', value, onChange, placeholder, disabled, autoComplete } ) => (
    <input
        id={ id }
        type={ type }
        value={ value }
        onChange={ onChange }
        placeholder={ placeholder }
        disabled={ disabled }
        autoComplete={ autoComplete }
        className="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800
                   placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-400
                   disabled:opacity-60 font-mono"
    />
);

/**
 * Inline feedback bar shown after a save attempt.
 * status: 'success' | 'error' | null
 */
const Feedback = ( { status, message } ) => {
    if ( ! status ) return null;

    const styles = status === 'success'
        ? 'bg-green-50 border-green-200 text-green-700'
        : 'bg-red-50 border-red-200 text-red-600';

    return (
        <div className={ `flex items-center gap-2 rounded-lg border px-3 py-2 text-xs ${ styles }` }>
            { status === 'success' ? (
                <svg className="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={ 2 } d="M5 13l4 4L19 7" />
                </svg>
            ) : (
                <svg className="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={ 2 } d="M12 9v2m0 4h.01M12 3a9 9 0 100 18A9 9 0 0012 3z" />
                </svg>
            ) }
            { message }
        </div>
    );
};

/* -------------------------------------------------------------------------- */
/* PlatformCard                                                                */
/* -------------------------------------------------------------------------- */

/**
 * A self-contained save-form for a single platform.
 *
 * Props:
 *   title       string
 *   icon        ReactNode
 *   platform    'facebook' | 'linkedin'
 *   fields      Array<{ id, label, key, type, placeholder, autoComplete }>
 *   initial     Object  – current values from the API
 */
const PlatformCard = ( { title, icon, platform, fields, initial } ) => {
    const { apiBase } = window.socialSyncEngine ?? {};

    const emptyValues = () =>
        Object.fromEntries( fields.map( ( f ) => [ f.key, '' ] ) );

    const [ values,   setValues   ] = useState( emptyValues );
    const [ saving,   setSaving   ] = useState( false );
    const [ feedback, setFeedback ] = useState( null ); // { status, message }
    const [ show,     setShow     ] = useState( {} );   // per-field secret visibility

    // Populate fields once initial data arrives.
    useEffect( () => {
        if ( initial ) {
            setValues( Object.fromEntries( fields.map( ( f ) => [ f.key, initial[ f.key ] ?? '' ] ) ) );
        }
    }, [ initial ] );

    const handleChange = ( key, val ) => {
        setValues( ( prev ) => ( { ...prev, [ key ]: val } ) );
        setFeedback( null );
    };

    const handleSubmit = async ( e ) => {
        e.preventDefault();
        setSaving( true );
        setFeedback( null );

        // Guard – require all fields.
        const missing = fields.find( ( f ) => ! values[ f.key ]?.trim() );
        if ( missing ) {
            setFeedback( { status: 'error', message: `${ missing.label } is required.` } );
            setSaving( false );
            return;
        }

        try {
            await axios.post( `${ apiBase }/oauth/settings`, {
                platform,
                ...values,
            } );

            setFeedback( { status: 'success', message: 'Credentials saved successfully.' } );

            // Mask secret fields again after a successful save.
            const masked = { ...values };
            fields
                .filter( ( f ) => f.type === 'password' )
                .forEach( ( f ) => { masked[ f.key ] = '••••••••'; } );
            setValues( masked );

        } catch ( err ) {
            const msg = err.response?.data?.message ?? err.message ?? 'Save failed.';
            setFeedback( { status: 'error', message: msg } );
        } finally {
            setSaving( false );
        }
    };

    const toggleShow = ( key ) =>
        setShow( ( prev ) => ( { ...prev, [ key ]: ! prev[ key ] } ) );

    return (
        <div className="rounded-2xl border border-gray-200 bg-white shadow-sm overflow-hidden">
            {/* Card header */}
            <div className="flex items-center gap-3 px-5 py-4 border-b border-gray-100 bg-gray-50">
                { icon }
                <h3 className="text-sm font-semibold text-gray-800">{ title }</h3>
            </div>

            {/* Form */}
            <form onSubmit={ handleSubmit } className="px-5 py-5 space-y-4">
                { fields.map( ( field ) => {
                    const isSecret  = field.type === 'password';
                    const visible   = show[ field.key ];
                    const inputType = isSecret ? ( visible ? 'text' : 'password' ) : 'text';

                    return (
                        <div key={ field.key }>
                            <Label htmlFor={ field.id } required>{ field.label }</Label>
                            <div className="relative">
                                <Input
                                    id={ field.id }
                                    type={ inputType }
                                    value={ values[ field.key ] ?? '' }
                                    onChange={ ( e ) => handleChange( field.key, e.target.value ) }
                                    placeholder={ field.placeholder }
                                    disabled={ saving }
                                    autoComplete={ field.autoComplete ?? 'off' }
                                />
                                { isSecret && (
                                    <button
                                        type="button"
                                        onClick={ () => toggleShow( field.key ) }
                                        className="absolute inset-y-0 right-2 flex items-center text-gray-400 hover:text-gray-600 focus:outline-none"
                                        aria-label={ visible ? 'Hide' : 'Show' }
                                    >
                                        { visible ? (
                                            /* Eye-off */
                                            <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={ 2 }
                                                    d="M13.875 18.825A10.05 10.05 0 0112 19c-5 0-9-4-9-7s4-7 9-7a10.05 10.05 0 011.875.175M15 12a3 3 0 11-6 0 3 3 0 016 0zm5.659-3.34A11.96 11.96 0 0121 12c0 3-4 7-9 7a11.96 11.96 0 01-2.34-.325M3 3l18 18" />
                                            </svg>
                                        ) : (
                                            /* Eye */
                                            <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={ 2 }
                                                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={ 2 }
                                                    d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                            </svg>
                                        ) }
                                    </button>
                                ) }
                            </div>
                            { field.hint && (
                                <p className="mt-1 text-xs text-gray-400">{ field.hint }</p>
                            ) }
                        </div>
                    );
                } ) }

                <Feedback status={ feedback?.status } message={ feedback?.message } />

                <button
                    type="submit"
                    disabled={ saving }
                    className="flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 disabled:bg-indigo-400
                               text-white text-sm font-semibold px-5 py-2 rounded-lg transition-colors
                               focus:outline-none focus:ring-2 focus:ring-indigo-400"
                >
                    { saving ? (
                        <>
                            <span className="animate-spin w-4 h-4 border-2 border-white border-t-transparent rounded-full" />
                            Saving…
                        </>
                    ) : 'Save Credentials' }
                </button>
            </form>
        </div>
    );
};

/* -------------------------------------------------------------------------- */
/* Platform icons                                                              */
/* -------------------------------------------------------------------------- */

const FacebookIcon = () => (
    <svg className="w-7 h-7 text-[#1877F2]" viewBox="0 0 24 24" fill="currentColor">
        <path d="M24 12.073C24 5.404 18.627 0 12 0S0 5.404 0 12.073C0 18.1 4.388 23.094 10.125 24v-8.437H7.078v-3.49h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.49h-2.796V24C19.612 23.094 24 18.1 24 12.073z" />
    </svg>
);

const LinkedInIcon = () => (
    <svg className="w-7 h-7 text-[#0A66C2]" viewBox="0 0 24 24" fill="currentColor">
        <path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433a2.062 2.062 0 01-2.063-2.065 2.064 2.064 0 112.063 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z" />
    </svg>
);

/* -------------------------------------------------------------------------- */
/* Field definitions                                                           */
/* -------------------------------------------------------------------------- */

const FACEBOOK_FIELDS = [
    {
        id:          'fb-app-id',
        key:         'app_id',
        label:       'App ID',
        type:        'text',
        placeholder: 'e.g. 123456789012345',
        autoComplete: 'off',
        hint:        'Found in your Facebook Developer Console → App Dashboard.',
    },
    {
        id:          'fb-app-secret',
        key:         'app_secret',
        label:       'App Secret',
        type:        'password',
        placeholder: 'Enter your App Secret',
        autoComplete: 'new-password',
        hint:        'Keep this secret – never expose it in client-side code.',
    },
];

const LINKEDIN_FIELDS = [
    {
        id:          'li-client-id',
        key:         'client_id',
        label:       'Client ID',
        type:        'text',
        placeholder: 'e.g. 86abcdefghij12',
        autoComplete: 'off',
        hint:        'Found in your LinkedIn Developer App → Auth tab.',
    },
    {
        id:          'li-client-secret',
        key:         'client_secret',
        label:       'Client Secret',
        type:        'password',
        placeholder: 'Enter your Client Secret',
        autoComplete: 'new-password',
        hint:        'Keep this secret – never expose it in client-side code.',
    },
];

/* -------------------------------------------------------------------------- */
/* Settings page                                                               */
/* -------------------------------------------------------------------------- */

const Settings = () => {
    const { apiBase } = window.socialSyncEngine ?? {};

    const [ credentials, setCredentials ] = useState( null );
    const [ loading,     setLoading     ] = useState( true );
    const [ loadError,   setLoadError   ] = useState( '' );

    useEffect( () => {
        axios
            .get( `${ apiBase }/oauth/settings` )
            .then( ( { data } ) => setCredentials( data ) )
            .catch( () => setLoadError( 'Could not load saved credentials.' ) )
            .finally( () => setLoading( false ) );
    }, [] );

    return (
        <div className="max-w-2xl mx-auto space-y-6">

            {/* Page title + hint */}
            <div>
                <h2 className="text-base font-semibold text-gray-800">API Credentials</h2>
                <p className="text-sm text-gray-500 mt-0.5">
                    Enter your platform app credentials. These are stored securely in your WordPress
                    database and used only for server-side API calls.
                </p>
            </div>

            { loading && (
                <div className="flex items-center justify-center h-32">
                    <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600" />
                </div>
            ) }

            { loadError && (
                <div className="rounded-xl bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-600">
                    { loadError }
                </div>
            ) }

            { ! loading && ! loadError && (
                <>
                    <PlatformCard
                        title="Facebook App Credentials"
                        icon={ <FacebookIcon /> }
                        platform="facebook"
                        fields={ FACEBOOK_FIELDS }
                        initial={ credentials?.facebook }
                    />

                    <PlatformCard
                        title="LinkedIn App Credentials"
                        icon={ <LinkedInIcon /> }
                        platform="linkedin"
                        fields={ LINKEDIN_FIELDS }
                        initial={ credentials?.linkedin }
                    />

                    {/* Where-to-find-these guide */}
                    <div className="rounded-2xl border border-blue-100 bg-blue-50 px-5 py-4 space-y-2">
                        <p className="text-xs font-semibold text-blue-700 uppercase tracking-wide">
                            Where to find your credentials
                        </p>
                        <ul className="text-xs text-blue-700 space-y-1 list-disc list-inside">
                            <li>
                                <strong>Facebook:</strong> Visit{' '}
                                <a
                                    href="https://developers.facebook.com/apps"
                                    target="_blank"
                                    rel="noreferrer"
                                    className="underline hover:text-blue-900"
                                >
                                    developers.facebook.com/apps
                                </a>
                                , open your app → Settings → Basic.
                            </li>
                            <li>
                                <strong>LinkedIn:</strong> Visit{' '}
                                <a
                                    href="https://www.linkedin.com/developers/apps"
                                    target="_blank"
                                    rel="noreferrer"
                                    className="underline hover:text-blue-900"
                                >
                                    linkedin.com/developers/apps
                                </a>
                                , open your app → Auth tab.
                            </li>
                            <li>
                                After saving credentials, use the <strong>Publish</strong> tab to
                                connect your accounts via OAuth.
                            </li>
                        </ul>
                    </div>
                </>
            ) }
        </div>
    );
};

export default Settings;
