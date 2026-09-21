export default {
	plugins: {
		'postcss-nested': {},
		'postcss-preset-env': {
			features: {
				'nesting-rules': false,
			},
		},
	},
};
