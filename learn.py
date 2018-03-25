

# Load libraries
import pandas
import numpy

from sklearn import model_selection
from sklearn.discriminant_analysis import LinearDiscriminantAnalysis

names = ['hmgw', 'hmgd', 'hmgl', 'hmgs', 'hmgt', 'hstr', 'hppg', 'htsg', 'htss', 'amgw', 'amgd', 'amgl', 'amgs', 'amgt', 'astr', 'appg', 'atsg', 'atss', 'posdiff', 'md', 'res', 'off']

dataset = pandas.read_csv('data.csv', names=names)
data_array = dataset.values

input  = data_array[:,0:19]
result = data_array[:,20]
offset = data_array[:,21]

seed = 7
validation_size = 0.2

input_train, input_validation, result_train, validation = model_selection.train_test_split(input, result, test_size=validation_size, random_state=seed)
input_train, input_validation, offset_train, validation = model_selection.train_test_split(input, offset, test_size=validation_size, random_state=seed)




reslda = LinearDiscriminantAnalysis()
reslda.fit(input_train, result_train)

offlda = LinearDiscriminantAnalysis()
offlda.fit(input_train, offset_train)

bet_dataset = pandas.read_csv('validation.csv', names=names)
bet_array = bet_dataset.values
data = bet_array[:,0:19]

resultBets = reslda.predict(data);
offsetBets = offlda.predict(data);

results = numpy.column_stack((resultBets, offsetBets))

numpy.savetxt("results.csv", results.astype(int), fmt='%i', delimiter=",")
