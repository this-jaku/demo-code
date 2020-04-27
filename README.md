
## General info
Mobile folder contains class responsible for check does usage of application by used doesn't extend licenses limit.
In implementation there are models passed and mocks created in test class. It's not perfect, but creating interfaces just for the one test needs is over engineering (no one will reuse it, it's not standard in this project).

t2s is small class that parse text inline marker, and replace it with provided value.
When in example phone questionnaire needs to be play, it can be customized with data specify for studied group.
 
Let's say we got text:
```
You insurance will expired in [API:insurance:end-date:lang:en-US:DdMy], please call [API:contact-lines:2|spell-out] for more details.
```
and data taken from extenal provider
```
{
"API": {
	"insurance": {
			"number": "abc343434",
			"start-date": "11.12.2019",
			"end-date": "10.12.2020"
		},
	"contact-lines": {
			"+987 596566356",
			"+987 245656557",
			"+987 111111111",
		}
	}
}
```
the result will be
```
You insurance will expired in thursday 10 october 2020, please call +987 111111111 for more details.
```

