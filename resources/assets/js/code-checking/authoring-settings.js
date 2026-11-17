export function submissionFields(config) {
    const modes = new Set(config.submission_modes || []);
    const project = modes.has('project') || modes.has('repository');
    const source = config.language !== 'text';
    return {
        languages: source && (modes.has('code') || modes.has('files') || project),
        extensions: modes.has('files'),
        entrypoint: source && (modes.has('files') || project),
        limits: modes.has('files') || project,
    };
}

export function hasFloatComparison(config) {
    const floating = new Set(['float', 'float_binary64', 'rational_absolute']);
    return floating.has(config.comparison) || (config.tests || []).some(test => floating.has(test.comparison));
}

export function checkerPayload(config) {
    const payload = {...config};
    if (config.check_type !== 'tests') delete payload.test_config;
    else if(config.test_config && config.test_mode!=='suite' && !hasFloatComparison(config.test_config)){
        payload.test_config={...config.test_config};
        delete payload.test_config.absolute_tolerance;delete payload.test_config.relative_tolerance;
    }
    if(config.check_type!=='gpt')for(const key of ['gpt_model','gpt_rate_limit','reference_answer'])delete payload[key];
    const fields=submissionFields(config);
    if(!fields.languages)delete payload.allowed_languages;
    if(!fields.extensions)delete payload.allowed_extensions;
    if(!fields.entrypoint)delete payload.entrypoint;
    if(!fields.limits)for(const key of ['max_files','max_file_bytes','max_total_bytes'])delete payload[key];
    return payload;
}
